<?php

namespace Kagatan\MikBillDB;

use Illuminate\Database\Capsule\Manager as Capsule;


class BillingMikBillDB
{
    private $_system_options = [];

    public function __construct($db_host, $db_name, $db_login, $db_password, $port = 3306)
    {
        $capsule = new Capsule();

        $capsule->addConnection([
            'driver'   => 'mysql',
            'host'     => $db_host,
            'database' => $db_name,
            'username' => $db_login,
            'password' => $db_password,
            'port'     => $port,
            //'charset'   => 'koi8r',
            //            'collation' => 'utf8_unicode_ci',
            'prefix'   => '',
        ]);

        // Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();

        $this->switchKOI8R();

        $this->_system_options = $this->getSystemOptions();
    }


    private function switchUTF8()
    {
        Capsule::connection()->statement("SET NAMES utf8;");
        Capsule::connection()->statement("SET CHARSET utf8;");
    }

    private function switchKOI8R()
    {
        Capsule::connection()->statement("SET NAMES koi8r;");
        Capsule::connection()->statement("SET CHARSET koi8r;");
    }


    public function getPackets()
    {
        $result = Capsule::table('packets')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addPacket($packetData)
    {
        $packetData = self::convertICONV($packetData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('packets')->insert($packetData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function getSectors()
    {
        $result = Capsule::table('sectors')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addSector($sectorData)
    {
        $sectorData = self::convertICONV($sectorData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('sectors')->insert($sectorData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function getUsersGroups()
    {
        $result = Capsule::table('usersgroups')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addUsersGroup($usersGroupsData)
    {
        $usersGroupsData = self::convertICONV($usersGroupsData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('usersgroups')->insert($usersGroupsData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }

    public function getSwitchTypes()
    {
        $result = Capsule::table('switche_type')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addSwitchType($switchTypeData)
    {
        $switchTypeData = self::convertICONV($switchTypeData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('switche_type')->insert($switchTypeData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function addUserNotes($userNotesData)
    {
        $this->switchUTF8();
        Capsule::table('sticky_notes_user')->insert($userNotesData);
        $this->switchKOI8R();

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function getAddresses()
    {
        // Заполняем населенные пункты
        $result = $this->getSettlements();
        foreach ($result as $item) {
            $addresses[$item["settlementname"]] = $item;
            $addresses["neighborhoods"] = [];
            $addresses["lanes"] = [];
        }

        // Заполняем районы
        $result = $this->getNeighborhoods();
        foreach ($result as $item) {
            if (!isset($addresses[$item["settlementname"]]["neighborhoods"][$item["neighborhoodname"]])) {
                $addresses[$item["settlementname"]]["neighborhoods"][$item["neighborhoodname"]] = $item;
            }
        }

        // Заполняем улицы
        $result = $this->getLanes();
        foreach ($result as $item) {
            if (!isset($addresses[$item["settlementname"]]["lanes"][$item["lane"]])) {
                $addresses[$item["settlementname"]]["lanes"][$item["lane"]] = $item;
            }
        }

        // Заполняем дома
        $result = $this->getHouses();
        foreach ($result as $item) {
            if (!isset($addresses[$item["settlementname"]]["lanes"][$item["lane"]]["houses"][$item["house"]])) {
                $addresses[$item["settlementname"]]["lanes"][$item["lane"]]["houses"][$item["house"]] = $item;
            }
        }

        return $addresses;
    }

    public function addHouseToAddresses($settlementName, $neighborhoodName, $laneName, $house, $porch = '', $floor = '', &$addresses = [])
    {
        if (empty($address)) {
            $address = $this->getAddresses();
        }

        // Создадим нас. пункт если отсутсвует
        if (!isset($address[$settlementName])) {

            $settlementData = [
                "settlementname" => $settlementName
            ];
            $id = $this->addSettlement($settlementData);

            //Добавим информацию в глобальный массив $addresses
            $settlementData["settlementid"] = $id;
            $settlementData["neighborhoods"] = [];
            $settlementData["lanes"] = [];
            $address[$settlementName] = $settlementData;
        }

        // Создадим район в нас. пункте если отсутсвует
        if (!empty($neighborhoodName) and !isset($address[$settlementName]["neighborhoods"][$neighborhoodName])) {

            $neighborhoodData = [
                "neighborhoodname" => $neighborhoodName,
                "settlementid"     => $address[$settlementName]['settlementid']
            ];
            $id = $this->addNeighborhood($neighborhoodData);

            //Добавим информацию в глобальный массив $addresses
            $neighborhoodData["neighborhoodid"] = $id;
            $address[$settlementName]["neighborhoods"][$neighborhoodName] = $neighborhoodData;
        }

        // Создадим улицу в нас. пункте если отсутсвует
        if (!isset($address[$settlementName]["lanes"][$laneName])) {

            $laneData = [
                "lane"         => $laneName,
                "settlementid" => $address[$settlementName]['settlementid']
            ];
            $id = $this->addLane($laneData);

            //Добавим информацию в глобальный массив $addresses
            $laneData["laneid"] = $id;
            $laneData["houses"] = [];
            $address[$settlementName]["lanes"][$laneName] = $laneData;
        }

        // Создадим дом на улицу в нас. пункте если отсутсвует
        if (!isset($address[$settlementName]["lanes"][$laneName]["houses"][$house])) {

            // Район
            if (!empty($neighborhoodName)) {
                $neighborhoodId = $address[$settlementName]["neighborhoods"][$neighborhoodName]["neighborhoodid"];
            } else {
                $neighborhoodId = 0;
            }

            // Подъезд
            if (!empty((int)$porch)) {
                $porches = (int)$porch;
            } else {
                $porches = 4;
            }

            // Этаж
            if (!empty((int)$floor)) {
                $floors = (int)$floor;
            } else {
                $floors = 5;
            }

            $houseData = [
                "laneid"         => $address[$settlementName]["lanes"][$laneName]['laneid'],
                "neighborhoodid" => $neighborhoodId,
                "house"          => $house,
                "porches"        => $porches,
                "floors"         => $floors,
            ];
            $id = $this->addHouse($houseData);

            //Добавим информацию в глобальный массив $addresses
            $houseData["houseid"] = $id;
            $address[$settlementName]["lanes"][$laneName]["houses"][$house] = $houseData;
        } else {
            // Дом существует, проверим нужно ли что то обновить

            $houseData = [];

            // Подъезды
            if ((int)$porch > $address[$settlementName]["lanes"][$laneName]["houses"][$house]["porches"]) {


                // Изменим дом
                $houseData = [
                    "porches" => (int)$porch,
                ];

                // Обновим информацию в глобальном массиве $addresses
                $address[$settlementName]["lanes"][$laneName]["houses"][$house]["porches"] = (int)$porch;
            }

            // Этажи
            if ((int)$floor > $address[$settlementName]["lanes"][$laneName]["houses"][$house]["floors"]) {

                // Изменим дом
                $houseData = [
                    "floors" => (int)$floor,
                ];

                // Обновим информацию в глобальном массиве $addresses
                $address[$settlementName]["lanes"][$laneName]["houses"][$house]["floors"] = (int)$floor;
            }

            // Есть поля для обновления в доме
            if (!empty($houseData)) {
                $this->editHouse($address[$settlementName]["lanes"][$laneName]["houses"][$house]["houseid"], $houseData);
            }
        }

        return $address[$settlementName]["lanes"][$laneName]["houses"][$house]["houseid"];
    }


    public function deleteSettlement($settlementID)
    {
        Capsule::table("lanes_settlements")
            ->where("settlementid", "=", $settlementID)
            ->delete();
    }


    public function addSettlement($settlementData)
    {
        $settlementData = self::convertICONV($settlementData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('lanes_settlements')->insert($settlementData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function getSettlements()
    {
        $result = Capsule::table('lanes_settlements')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addNeighborhood($neighborhoodData)
    {
        $neighborhoodData = self::convertICONV($neighborhoodData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('lanes_neighborhoods')->insert($neighborhoodData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }

    public function getNeighborhoods()
    {
        $result = Capsule::table('lanes_neighborhoods')
            ->select('lanes_neighborhoods.*', 'lanes_settlements.settlementname')
            ->leftJoin('lanes_settlements', 'lanes_neighborhoods.settlementid', '=', 'lanes_settlements.settlementid')
            ->get();

        return self::convertICONV(self::toArray($result));
    }


    public function getHouse($houseId)
    {
        $result = Capsule::table('lanes_houses')
            ->select('lanes_houses.*', 'lanes.lane', 'lanes_settlements.settlementname')
            ->leftJoin('lanes', 'lanes.laneid', '=', 'lanes_houses.laneid')
            ->leftJoin('lanes_settlements', 'lanes.settlementid', '=', 'lanes_settlements.settlementid')
            ->where('lanes_houses.houseid', '=', $houseId)
            ->first();


        return self::convertICONV((array)$result);
    }


    public function addHouse($houseData)
    {
        $houseData = self::convertICONV($houseData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('lanes_houses')->insert($houseData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }

    public function editHouse($houseID, $houseData)
    {
        $houseData = self::convertICONV($houseData, "UTF-8", "KOI8-U//IGNORE");

        Capsule::table('lanes_houses')
            ->where("houseid", "=", $houseID)
            ->update($houseData);
    }


    public function getHouses()
    {
        $result = Capsule::table('lanes_houses')
            ->select('lanes_houses.*', 'lanes.lane', 'lanes_settlements.settlementname')
            ->leftJoin('lanes', 'lanes.laneid', '=', 'lanes_houses.laneid')
            ->leftJoin('lanes_settlements', 'lanes.settlementid', '=', 'lanes_settlements.settlementid')
            ->get();

        return self::convertICONV(self::toArray($result));
    }


    public function deleteLane($laneID)
    {
        Capsule::table("lanes")
            ->where("laneid", "=", $laneID)
            ->delete();
    }

    public function editLane($laneID, $laneData)
    {
        $laneData = self::convertICONV($laneData, "UTF-8", "KOI8-U//IGNORE");

        Capsule::table('lanes')
            ->where("laneid", "=", $laneID)
            ->update($laneData);
    }

    public function addLane($laneData)
    {
        $laneData = self::convertICONV($laneData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('lanes')->insert($laneData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function getLanes()
    {
        $result = Capsule::table('lanes')
            ->select('lanes.*', 'lanes_settlements.settlementname')
            ->leftJoin('lanes_settlements', 'lanes.settlementid', '=', 'lanes_settlements.settlementid')
            ->get();

        return self::convertICONV(self::toArray($result));
    }

    public function getServices()
    {
        $result = Capsule::table('services')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addUsersSevice($param)
    {
        Capsule::table('services_users_pairs')->where($param)->delete();
        Capsule::table('services_users_pairs')->insert($param);

        return Capsule::connection()->getPdo()->lastInsertId();
    }

    public function getSwitches()
    {
        $result = Capsule::table('switches')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function addSwitch($switchData)
    {
        $switchData = self::convertICONV($switchData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('switches')->insert($switchData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }

    public function getStuffPersonals()
    {
        $result = Capsule::table('stuff_personal')->select()->get();

        return self::convertICONV(self::toArray($result));
    }

    public function getDeviceTypes()
    {
        $result = Capsule::table('dev_types')->select()->get();

        return self::convertICONV(self::toArray($result));
    }


    public function addDeviceType($deviceTypeData)
    {
        $deviceTypeData = self::convertICONV($deviceTypeData, "UTF-8", "KOI8-U//IGNORE");
        Capsule::table('dev_types')->insert($deviceTypeData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }

    /**
     * Only bugh type 5,7
     *
     * @param $bughStatData
     * @return string
     */
    public function addBughStat($bughStatData)
    {
        Capsule::table('bugh_plategi_stat')->insert($bughStatData);
        return;
    }

    public function addPending($pendingData)
    {
        Capsule::table('users_pending_changes')->insert($pendingData);
        return;
    }

    public function addVouchers($vouchersData)
    {
        Capsule::table('mod_cards_cards')->insert($vouchersData);
        return;
    }

    public function addUserDevice($uid, $userDeviceData)
    {
        Capsule::table('dev_user')->insert([
            "uid"       => $uid,
            "devtypeid" => $userDeviceData["devtypeid"]
        ]);
        $id = Capsule::connection()->getPdo()->lastInsertId();

        foreach ($userDeviceData as $item => $value) {
            Capsule::table('dev_fields')->insert([
                "devid" => $id,
                "key"   => $item,
                "value" => $value,
            ]);
        }
    }


    public function getUserDevices($uid)
    {
        $devices = [];

        $result = Capsule::table('dev_user')
            ->select()
            ->where("uid", "=", $uid)
            ->get();
        foreach ($result as $item) {

            $dev = Capsule::table('dev_fields')
                ->select()
                ->where("devid", "=", $item->devid)
                ->get()
                ->pluck('value', 'key');


            $devices[] = $dev;
        }

        return $devices;
    }

    public function getUsers()
    {
        $users = [];
        $tables = ["users", "usersblok", "usersdel", "usersfreeze"];
        foreach ($tables as $table) {
            $result = Capsule::table($table)->select()->get();

            if (!empty($result)) {
                $users = array_merge($users, self::toArray($result));
            }
        }

        return self::convertICONV($users);
    }


    public function getUser($uid)
    {
        $tables = ["users", "usersblok", "usersdel", "usersfreeze"];
        foreach ($tables as $table) {
            $result = Capsule::table($table)
                ->select()
                ->where("uid", "=", $uid)
                ->first();

            if (!empty($result)) {
                return self::convertICONV((array)$result);
            }
        }

        return [];
    }


    public function addUser($userData, $table = "users")
    {
        $userData = self::convertICONV($userData, "UTF-8", "KOI8-U//IGNORE");

        if (empty($userData['local_ip']) and !empty($userData['sectorid'])) {
            $userData['local_ip'] = $this->getFirstIpFromSector($userData['sectorid']);
        }

        if (empty($userData['framed_ip'])) {
            $userData['framed_ip'] = $this->getFramedIPByLocalAndNET_VPN($userData['local_ip'], $this->_system_options["NET_VPN"]);
        }

        Capsule::table($table)->insert($userData);

        return Capsule::connection()->getPdo()->lastInsertId();
    }


    public function editUser($uid, $userData)
    {
        $userData = self::convertICONV($userData, "UTF-8", "KOI8-U//IGNORE");

        Capsule::table('users')
            ->where("uid", "=", $uid)
            ->update($userData);

        Capsule::table('usersfreeze')
            ->where("uid", "=", $uid)
            ->update($userData);

        Capsule::table('usersdel')
            ->where("uid", "=", $uid)
            ->update($userData);

        Capsule::table('usersblok')
            ->where("uid", "=", $uid)
            ->update($userData);
    }


    public function getSectorFromIp($ip)
    {
        $result = Capsule::table("sectorspool")
            ->where("ip", "=", $ip)
            ->orderBy("ip2long", 'ASC')
            ->limit(1)
            ->first();

        if (!empty($result->sectorid)) {

            Capsule::table("sectorspool")
                ->where("sectorid", "=", $result->sectorid)
                ->where("ip", "=", $result->ip)
                ->delete();

            return $result->sectorid;
        }

        return false;
    }

    private function getFirstIpFromSector($sectorid)
    {
        $result = Capsule::table("sectorspool")
            ->where("sectorid", "=", $sectorid)
            ->orderBy("ip2long", 'ASC')
            ->limit(1)
            ->first();


        if (!empty($result->ip)) {
            Capsule::table("sectorspool")
                ->where("sectorid", "=", $sectorid)
                ->where("ip", "=", $result->ip)
                ->delete();

            return $result->ip;
        } else {
            echo "Закончился сегмент. ID: " . $sectorid;
            exit;
        }

        return false;
    }

    public function getSystemOptions()
    {
        return Capsule::table("system_options")
            ->select()
            ->get()
            ->pluck('value', 'key');
    }

    public function addSystemOptions($options)
    {
        if (!empty($options)) {
            $insertData = [];
            foreach ($options as $key => $value) {
                $params = [
                    "key"   => $key,
                    "value" => $value
                ];

                Capsule::table("system_options")->where('key', $key)->delete();
                Capsule::table("system_options")->insert($params);
            }
        }

        return true;
    }

    public function addUsersCustomFields($uid, $customFieldsData)
    {
        if (!empty($customFieldsData)) {
            $insertData = [];
            foreach ($customFieldsData as $key => $value) {
                $insertData[] = [
                    "uid"   => $uid,
                    "key"   => $key,
                    "value" => $value
                ];

                Capsule::table("users_custom_fields")
                    ->where("uid", $uid)
                    ->where("key", $key)
                    ->delete();
            }

            Capsule::table("users_custom_fields")->insert($insertData);
        }

        return true;
    }


    public function setUserGroupId($uid, $usersgroupid)
    {
        Capsule::table("usersgroups_users")
            ->where("uid", $uid)
            ->where("usersgroupid", $usersgroupid)
            ->delete();

        Capsule::table("usersgroups_users")->insert([
            "uid"          => $uid,
            "usersgroupid" => $usersgroupid,
        ]);
    }

    ######


    public static function toArray($object)
    {
        $result = [];

        if (is_object($object)) {
            foreach ($object as $item) {
                if (is_object($item)) {
                    $result[] = (array)$item;
                }
            }
        }

        return $result;
    }

    public static function convertICONV($array, $in = "KOI8-U", $out = "UTF-8")
    {
        if (is_array($array)) {
            foreach ($array as $key => $row) {
                $array[$key] = self::convertICONV($row, $in, $out);
            }

            return $array;
        } else {
            return iconv($in, $out, $array);
        }
    }


    public static function prepareArray($key, $dataArray, $key2 = false)
    {
        $result = [];

        foreach ($dataArray as $row) {
            if (!empty($row[$key])) {
                if ($key2 !== false) {
                    $result[$row[$key]] = $row[$key2];
                } else {
                    if (is_object($row)) {
                        $tmp = [];
                        foreach ($row as $item => $value) {
                            $tmp[$item] = $value;
                        }
                    } else {
                        $tmp = $row;
                    }

                    $result[$row[$key]] = $tmp;
                }
            }
        }

        return $result;
    }

    private function getFramedIPByLocalAndNET_VPN($local_IP, $NET_VPN)
    {
        $nets = explode(".", $local_IP);

        $framed_ip = $NET_VPN . $nets[2] . '.' . $nets[3];

        return $framed_ip;
    }


}
