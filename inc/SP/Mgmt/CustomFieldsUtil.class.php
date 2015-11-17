<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2015 Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace SP\Mgmt;

/**
 * Class CustomFieldsUtil utilidades para los campos personalizados
 *
 * @package SP\Mgmt
 */
class CustomFieldsUtil
{
    public static function updateCustonFields(array &$fields, $accountId)
    {
        foreach ($fields as $id => $value) {
            $CustomFields = new CustomFields($id, $accountId, $value);
            $CustomFields->updateCustomField();
        }

        return true;
    }

    public static function checkHash(&$fields, $srcHhash)
    {
        if (!is_array($fields)){
            return true;
        }

        $hash = '';

        foreach ($fields as $value) {
            $hash .= $value;
        }

        if (!empty($hash)) {
            return ($srcHhash == md5($hash));
        }

        return true;
    }
}