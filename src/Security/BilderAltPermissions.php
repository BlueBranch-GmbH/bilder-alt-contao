<?php

declare(strict_types=1);

namespace Bluebranch\BilderAlt\Security;

use Contao\BackendUser;
use Contao\StringUtil;

class BilderAltPermissions
{
    /**
     * Check if the user has permission to create single alt descriptions
     */
    public static function canCreateSingle(): bool
    {
        return self::hasPermission('create_single', 'bilder_alt');
    }

    /**
     * Check if the user has permission to create batch alt descriptions
     */
    public static function canCreateBatch(): bool
    {
        return self::hasPermission('create_batch', 'bilder_alt');
    }

    /**
     * Check if the user has permission to automatically create alt descriptions on upload
     */
    public static function canCreateAutoUpload(): bool
    {
        return self::hasPermission('create_auto_upload', 'bilder_alt');
    }

    /**
     * Check if the user has permission to generate a single page title
     */
    public static function canCreatePageTitle(): bool
    {
        return self::hasPermission('create_page_title', 'seiten_alt');
    }

    /**
     * Check if the user has permission to generate a single page description
     */
    public static function canCreatePageDescription(): bool
    {
        return self::hasPermission('create_page_description', 'seiten_alt');
    }

    /**
     * Check if the user has permission to batch-generate page SEO texts
     */
    public static function canCreatePageBatch(): bool
    {
        return self::hasPermission('create_page_batch', 'seiten_alt');
    }

    /**
     * Helper method to check if user has a specific permission in a given field
     */
    private static function hasPermission(string $permission, string $field): bool
    {
        $user = BackendUser::getInstance();

        // No user context (e.g. console commands)
        if (!$user instanceof BackendUser) {
            return false;
        }

        // Check if user object has required properties
        if (!isset($user->admin)) {
            return false;
        }

        // Admins always have permission
        if ($user->admin) {
            return true;
        }

        // For users with 'extend' or 'custom' inherit mode, check their individual permissions
        if (isset($user->inherit) && in_array($user->inherit, ['extend', 'custom'])) {
            if (isset($user->$field)) {
                $userPermissions = StringUtil::deserialize($user->$field, true);
                if (in_array($permission, $userPermissions)) {
                    return true;
                }
            }
        }

        // Check inherited group permissions (only if inherit is 'extend' or 'group')
        if (isset($user->inherit) && in_array($user->inherit, ['extend', 'group']) && isset($user->groups) && is_array($user->groups) && !empty($user->groups)) {
            $db = \Contao\Database::getInstance();
            $groups = $db->execute(
                "SELECT {$field} FROM tl_user_group WHERE id IN(" .
                implode(',', array_map('intval', $user->groups)) . ")"
            );

            while ($groups->next()) {
                $groupPermissions = StringUtil::deserialize($groups->$field, true);
                if (in_array($permission, $groupPermissions)) {
                    return true;
                }
            }
        }

        return false;
    }
}
