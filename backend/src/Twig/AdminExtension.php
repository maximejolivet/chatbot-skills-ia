<?php

namespace App\Twig;

use App\Entity\WorkflowStep;
use App\Enum\WorkflowStepType;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Grid\Definition\Field;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Generic field-value formatting for the admin backoffice grids -- reads a
 * (possibly dotted, e.g. "category.name") property path off any entity and
 * renders it as a readable string, regardless of the underlying PHP type
 * (backed enum, DateTimeInterface, bool, array, Doctrine collection/entity).
 */
final class AdminExtension extends AbstractExtension
{
    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        private readonly GridProviderInterface $gridProvider,
        private readonly RouterInterface $router,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_field_value', $this->fieldValue(...)),
            new TwigFunction('admin_route', $this->route(...)),
            new TwigFunction('admin_nav', $this->nav(...)),
            new TwigFunction('admin_grid_fields', $this->gridFields(...)),
            new TwigFunction('admin_step_type_label', $this->stepTypeLabel(...)),
            new TwigFunction('admin_step_summary', $this->stepSummary(...)),
        ];
    }

    public function stepTypeLabel(WorkflowStep $step): string
    {
        return match ($step->getStepType()) {
            WorkflowStepType::ApiCall => 'Appel API',
            WorkflowStepType::Email => 'Email',
            WorkflowStepType::Notification => 'Notification',
            WorkflowStepType::DataTransform => 'Transformation de données',
            WorkflowStepType::Condition => 'Condition',
            WorkflowStepType::Delay => 'Délai',
            WorkflowStepType::Webhook => 'Webhook',
        };
    }

    /**
     * One-line, plain-French description of what a step actually does when
     * it runs -- built from its real configuration (App\Workflow\
     * WorkflowExecutionService's handle*() methods), not a free-text field
     * an editor could let drift out of sync with the behavior.
     */
    public function stepSummary(WorkflowStep $step): string
    {
        $c = $step->getConfiguration();

        return match ($step->getStepType()) {
            WorkflowStepType::Email => \sprintf(
                'Envoie un email à %s — sujet : « %s »',
                $c['to_email'] ?? '?',
                $c['subject'] ?? '?',
            ),
            WorkflowStepType::ApiCall => \sprintf(
                '%s %s',
                mb_strtoupper($c['method'] ?? 'GET'),
                $c['url'] ?? '?',
            ),
            WorkflowStepType::Webhook => \sprintf(
                '%s vers %s, avec les données reçues en entrée',
                mb_strtoupper($c['method'] ?? 'POST'),
                $c['url'] ?? '?',
            ),
            WorkflowStepType::Notification => isset($c['webhook_url'])
                ? \sprintf('Envoie « %s » au webhook %s (canal : %s)', $c['message'] ?? '?', $c['webhook_url'], $c['channel'] ?? 'general')
                : \sprintf('Log uniquement (canal : %s) — aucun envoi réel, faute de webhook_url configuré', $c['channel'] ?? 'general'),
            WorkflowStepType::DataTransform => \sprintf(
                'Modifie les données : %s',
                implode(', ', array_map(
                    static fn(array $t): string => \sprintf('%s(%s)', $t['operation'] ?? '?', $t['field'] ?? '?'),
                    $c['transformations'] ?? [],
                )) ?: 'aucune transformation configurée',
            ),
            WorkflowStepType::Condition => isset($c['condition'])
                ? \sprintf('Si %s %s %s, exécute une action, sinon une autre', $c['condition']['field'] ?? '?', $c['condition']['operator'] ?? '?', $c['condition']['value'] ?? '?')
                : 'Aucune condition configurée — laisse passer les données telles quelles',
            WorkflowStepType::Delay => \sprintf('Attend %d secondes avant l\'étape suivante', (int) ($c['delay_seconds'] ?? 0)),
        };
    }

    /**
     * @return Field[]
     */
    public function gridFields(string $resourceName): array
    {
        return $this->gridProvider->get("app_{$resourceName}")->getEnabledFields();
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, resource: string, route: string}>}>
     */
    public function nav(): array
    {
        $item = fn(string $label, string $resource): array => [
            'label' => $label,
            'resource' => $resource,
            'route' => "app_admin_{$resource}_index",
        ];

        $groups = [
            [
                'label' => 'Analytics',
                'items' => [
                    $item('Vue d\'ensemble', 'analytics'),
                ],
            ],
            [
                'label' => 'IA & Vecteurs',
                'items' => [
                    $item('Providers IA', 'ai_provider_config'),
                    $item('Index vectoriels', 'vector_index'),
                    $item('Recherches (log)', 'search_query'),
                ],
            ],
            [
                'label' => 'Base de connaissances',
                'items' => [
                    $item('Documents', 'document'),
                    $item('Catégories', 'document_category'),
                    $item('Collections', 'collection'),
                    $item('FAQ', 'faq'),
                ],
            ],
            [
                'label' => 'Workflows',
                'items' => [
                    $item('Workflows', 'workflow'),
                    $item('Exécutions', 'workflow_execution'),
                ],
            ],
            [
                'label' => 'Chat',
                'items' => [
                    $item('Agents IA', 'ai_agent'),
                    $item('Conversations', 'conversation'),
                    $item('Messages', 'message'),
                ],
            ],
        ];

        // Only link to resources whose routes are already registered, so the
        // sidebar keeps working while the backoffice is built out incrementally.
        $routes = $this->router->getRouteCollection();
        foreach ($groups as $i => $group) {
            $groups[$i]['items'] = array_values(array_filter(
                $group['items'],
                static fn(array $item): bool => $routes->get($item['route']) instanceof \Symfony\Component\Routing\Route,
            ));
        }

        return array_values(array_filter($groups, static fn(array $group): bool => [] !== $group['items']));
    }

    public function fieldValue(object $row, string $path): string
    {
        try {
            $value = $this->propertyAccessor->getValue($row, $path);
        } catch (\Throwable) {
            return '';
        }

        return $this->format($value);
    }

    public function route(string $resourceName, string $action): string
    {
        return "app_admin_{$resourceName}_{$action}";
    }

    private function format(mixed $value): string
    {
        return match (true) {
            null === $value => '—',
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            \is_bool($value) => $value ? '✓' : '—',
            $value instanceof Collection => (string) $value->count(),
            \is_array($value) => (string) count($value),
            \is_object($value) => $this->formatObject($value),
            '' === $value => '—',
            default => (string) $value,
        };
    }

    private function formatObject(object $value): string
    {
        foreach (['getName', 'getTitle', 'getQuestion', '__toString'] as $method) {
            if (method_exists($value, $method)) {
                return (string) $value->{$method}();
            }
        }

        return '#' . ($this->propertyAccessor->isReadable($value, 'id') ? $this->propertyAccessor->getValue($value, 'id') : '');
    }
}
