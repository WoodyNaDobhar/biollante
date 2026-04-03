/**
 * Vue 3 composable for checking user permissions and roles.
 *
 * Replaces the `laravel-permission-to-vuejs` npm package.
 * Reads permissions from Inertia shared props instead of window.Laravel.
 *
 * Usage:
 *   import { usePermissions } from '@/composables/usePermissions';
 *
 *   const { can, is, permissions, roles } = usePermissions();
 *
 *   // Check a single permission
 *   if (can('display events')) { ... }
 *
 *   // Check multiple permissions (user must have ALL)
 *   if (can('display events', 'store events')) { ... }
 *
 *   // Check a single role
 *   if (is('Admin')) { ... }
 *
 *   // Check multiple roles (user must have at least ONE)
 *   if (is('Admin', 'Chapter Organizer')) { ... }
 *
 *   // In templates:
 *   <button v-if="can('store events')">Create Event</button>
 */

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface JsPermissions {
    roles: string[];
    permissions: string[];
}

export function usePermissions() {
    const page = usePage();

    const parsed = computed<JsPermissions>(() => {
        const raw = page.props?.auth?.jsPermissions;

        if (!raw) {
            return { roles: [], permissions: [] };
        }

        if (typeof raw === 'string') {
            try {
                return JSON.parse(raw);
            } catch {
                return { roles: [], permissions: [] };
            }
        }

        return raw as JsPermissions;
    });

    const permissions = computed(() => parsed.value.permissions);
    const roles = computed(() => parsed.value.roles);

    /**
     * Check if the user has ALL of the given permissions.
     */
    function can(...requiredPermissions: string[]): boolean {
        const userPermissions = permissions.value;
        return requiredPermissions.every((p) => userPermissions.includes(p));
    }

    /**
     * Check if the user has at least ONE of the given roles.
     */
    function is(...requiredRoles: string[]): boolean {
        const userRoles = roles.value;
        return requiredRoles.some((r) => userRoles.includes(r));
    }

    return {
        can,
        is,
        permissions,
        roles,
    };
}