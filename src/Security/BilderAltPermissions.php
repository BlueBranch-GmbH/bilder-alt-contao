<?php

declare(strict_types=1);

namespace Bluebranch\BilderAlt\Security;

use Contao\BackendUser;
use Contao\StringUtil;

class BilderAltPermissions
{
    public static function canCreateSingle(): bool
    {
        return self::hasPermission('create_single', 'bilder_alt');
    }

    public static function canCreateBatch(): bool
    {
        return self::hasPermission('create_batch', 'bilder_alt');
    }

    public static function canCreateAutoUpload(): bool
    {
        return self::hasPermission('create_auto_upload', 'bilder_alt');
    }

    public static function canCreatePageTitle(): bool
    {
        return self::hasPermission('create_page_title', 'seiten_alt');
    }

    public static function canCreatePageDescription(): bool
    {
        return self::hasPermission('create_page_description', 'seiten_alt');
    }

    public static function canCreatePageBatch(): bool
    {
        return self::hasPermission('create_page_batch', 'seiten_alt');
    }

    private static function hasPermission(string $permission, string $field): bool
    {
        $user = BackendUser::getInstance();

        if (!$user instanceof BackendUser) {
            return false;
        }

        if (!isset($user->admin)) {
            return false;
        }

        if ($user->admin) {
            return true;
        }

        $inherit = $user->inherit ?? null;

        if (in_array($inherit, ['extend', 'custom'], true) && isset($user->$field)) {
            $userPermissions = StringUtil::deserialize($user->$field, true);
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        if (in_array($inherit, ['extend', 'group'], true) && isset($user->groups) && is_array($user->groups) && !empty($user->groups)) {
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
