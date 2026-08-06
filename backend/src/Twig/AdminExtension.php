<?php

namespace App\Twig;

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
        ];
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
        $item = fn (string $label, string $resource) => [
            'label' => $label,
            'resource' => $resource,
            'route' => "app_admin_{$resource}_index",
        ];

        $groups = [
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
                static fn (array $item) => null !== $routes->get($item['route']),
            ));
        }

        return array_values(array_filter($groups, static fn (array $group) => [] !== $group['items']));
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

        return '#'.($this->propertyAccessor->isReadable($value, 'id') ? $this->propertyAccessor->getValue($value, 'id') : '');
    }
}
