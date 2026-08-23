import { Controller } from '@hotwired/stimulus';

/**
 * Client-side filter for the message list on the conversation show page --
 * the whole conversation is already loaded in the DOM (backend/templates/
 * admin/conversation/show.html.twig), so filtering in JS avoids a
 * round-trip for what's essentially a substring/role match over data
 * already on the page.
 */
export default class extends Controller {
    static targets = ['search', 'role', 'item', 'empty'];

    filter() {
        const term = this.searchTarget.value.trim().toLowerCase();
        const role = this.hasRoleTarget ? this.roleTarget.value : '';
        let visibleCount = 0;

        this.itemTargets.forEach((item) => {
            const matchesText = term === '' || item.textContent.toLowerCase().includes(term);
            const matchesRole = role === '' || item.dataset.role === role;
            const visible = matchesText && matchesRole;

            item.classList.toggle('hidden', !visible);
            if (visible) {
                visibleCount += 1;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', visibleCount !== 0);
        }
    }
}
