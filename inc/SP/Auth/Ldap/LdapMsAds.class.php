<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
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
 * Class LdapAds
 *
 * Autentificación basada en Active Directory
 *
 * @package SP\Auth\Ldap
 */
class LdapMsAds extends LdapBase
{

    /**
     * Devolver el filtro para comprobar la pertenecia al grupo
     *
     * @return mixed
     */
    protected function getGroupDnFilter()
    {
        if (empty($this->group)) {
            return '(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject))';
        } else {
            $groupDN = $this->searchGroupDN();

            return '(&(|(memberOf=' . $groupDN . ')(groupMembership=' . $groupDN . ')(memberof:1.2.840.113556.1.4.1941:=' . $groupDN . '))(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject)))';
        }
    }

    /**
     * Obtener el servidor de LDAP a utilizar
     *
     * @return mixed
     */
    protected function pickServer()
    {
        $server = Config::getConfig()->getLdapServer();

        if (preg_match('/[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}/', $server)) {
            return $server;
        }

        $serverDomain = '';
        $serverFQDN = explode('.', $server);

        for ($i = 1; $i <= count($serverFQDN) - 1; $i++) {
            $serverDomain .= $serverFQDN[$i] . '.';
        }

        $dnsServerQuery = '_msdcs.' . $serverDomain;
        $records = dns_get_record($dnsServerQuery, DNS_NS);

        if (count($records) === 0) {
            return $server;
        }

        $ads = [];

        foreach ($records as $record) {
            $ads[] = $record['target'];
        };

        $nAds = count($ads);

        return $nAds > 0 ? $ads[mt_rand(0, $nAds)] : $server;
    }

    /**
     * Obtener el filtro para buscar el usuario
     *
     * @return mixed
     */
    protected function getUserDnFilter()
    {
        return '(&(|(samaccountname=' . $this->userLogin . ')(cn=' . $this->userLogin . ')(uid=' . $this->userLogin . '))(|(objectClass=inetOrgPerson)(objectClass=person)(objectClass=simpleSecurityObject))(objectCategory=person))';
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

        $groupDN = $this->LdapAuthData->getGroupDn();
        $filter = '(memberof:1.2.840.113556.1.4.1941:=' . $groupDN . ')';

        $searchRes = @ldap_search($this->ldapHandler, $this->searchBase, $filter, ['sAMAccountName']);

        if (!$searchRes) {
            $Log->setLogLevel(Log::ERROR);
            $Log->addDescription(__('Error al buscar el grupo de usuarios', false));
            $Log->addDetails(__('Grupo', false), $groupDN);
            $Log->addDetails('LDAP ERROR', sprintf('%s (%d)', ldap_error($this->ldapHandler), ldap_errno($this->ldapHandler)));
            $Log->addDetails('LDAP FILTER', $filter);
            $Log->writeLog();

            throw new SPException(SPException::SP_ERROR, $Log->getDescription());
        }

        if (@ldap_count_entries($this->ldapHandler, $searchRes) === 0) {
            $Log->setLogLevel(Log::ERROR);
            $Log->addDescription(__('No se encontró el grupo con ese nombre', false));
            $Log->addDetails(__('Grupo', false), $groupDN);
            $Log->addDetails('LDAP ERROR', sprintf('%s (%d)', ldap_error($this->ldapHandler), ldap_errno($this->ldapHandler)));
            $Log->addDetails('LDAP FILTER', $filter);
            $Log->writeLog();

            throw new SPException(SPException::SP_ERROR, $Log->getDescription());
        }

        $entries = ldap_get_entries($this->ldapHandler, $searchRes);

        foreach ($entries as $entry) {
            if (is_array($entry)) {
                if ($this->userLogin === strtolower($entry['samaccountname'][0])) {
                    $Log->addDescription(__('Usuario verificado en grupo', false));
                    $Log->addDetails(__('Grupo', false), $groupDN);
                    $Log->writeLog();

                    return true;
                }
            }
        }

        $Log->addDescription(__('Usuario no pertenece al grupo', false));
        $Log->addDetails(__('Usuario', false), $this->LdapAuthData->getDn());
        $Log->addDetails(__('Grupo', false), $groupDN);

        return false;
    }

    /**
     * @return bool
     */
    protected function connect()
    {
        parent::connect();

        @ldap_set_option($this->ldapHandler, LDAP_OPT_REFERRALS, 0);

        return true;
    }
}