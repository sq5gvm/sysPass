<?php
/**
 * sysPass
 *
 * @author nuxsmin
 * @link http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
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
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Auth\Ldap;

use SP\Config\Config;
use SP\Core\Exceptions\SPException;
use SP\Log\Log;

/**
 * Class LdapStd
 *
 * Autentificación basada en LDAP estándard
 *
 * @package SP\Auth\Ldap
 */
class LdapStd extends LdapBase
{
    /**
     * Devolver el filtro para comprobar la pertenecia al grupo
     *
     * @return mixed
     */
    protected function getGroupDnFilter()
    {
        if (empty($this->group)){
            return '(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject))';
        } else {
            $groupDN = $this->searchGroupDN();

            return '(&(|(memberOf=' . $groupDN . ')(groupMembership=' . $groupDN . '))(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject)))';
        }
    }

    /**
     * Obtener el servidor de LDAP a utilizar
     *
     * @return mixed
     */
    protected function pickServer()
    {
        return Config::getConfig()->getLdapServer();
    }

    /**
     * Obtener el filtro para buscar el usuario
     *
     * @return mixed
     */
    protected function getUserDnFilter()
    {
        return '(&(|(samaccountname=' . $this->userLogin . ')(cn=' . $this->userLogin . ')(uid=' . $this->userLogin . '))(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject)))';
    }

    /**
     * Buscar al usuario en un grupo.
     *
     * @throws SPException
     * @return bool
     */
    protected function searchUserInGroup()
    {
        $Log = new Log(__FUNCTION__);

        // Comprobar si está establecido el filtro de grupo o el grupo coincide con
        // los grupos del usuario
        if (!$this->group
            || $this->group === '*'
            || in_array($this->LdapAuthData->getGroupDn(), $this->LdapAuthData->getGroups())
        ) {
            $Log->addDescription(__('Usuario verificado en grupo', false));
            $Log->writeLog();

            return true;
        }

        $userDN = $this->LdapAuthData->getDn();
        $groupName = $this->getGroupName() ?: $this->group;

        $filter = '(&(cn=' . $groupName . ')(|(member=' . $userDN . ')(uniqueMember=' . $userDN . '))(|(objectClass=groupOfNames)(objectClass=groupOfUniqueNames)(objectClass=group)))';

        $searchRes = @ldap_search($this->ldapHandler, $this->searchBase, $filter, ['member', 'uniqueMember']);

        if (!$searchRes) {
            $Log->setLogLevel(Log::ERROR);
            $Log->addDescription(__('Error al buscar el grupo de usuarios', false));
            $Log->addDetails(__('Grupo', false), $groupName);
            $Log->addDetails(__('Usuario', false), $userDN);
            $Log->addDetails('LDAP ERROR', sprintf('%s (%d)', ldap_error($this->ldapHandler), ldap_errno($this->ldapHandler)));
            $Log->addDetails('LDAP FILTER', $filter);
            $Log->writeLog();

            throw new SPException(SPException::SP_ERROR, $Log->getDescription());
        }

        if (@ldap_count_entries($this->ldapHandler, $searchRes) === 0) {
            $Log->addDescription(__('Usuario no pertenece al grupo', false));
            $Log->addDetails(__('Usuario', false), $userDN);
            $Log->addDetails(__('Grupo', false), $groupName);

            return false;
        }

        $Log->addDescription(__('Usuario verificado en grupo', false));
        $Log->addDescription($groupName);
        $Log->writeLog();

        return true;
    }
}