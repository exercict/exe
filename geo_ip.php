<?php
class Geo
{
    public function __construct($options = null) {

        $this->dirname = dirname(__file__);

        // ip
        if(!isset($options['ip']) OR !$this->is_valid_ip($options['ip']))
            $this->ip = $this->get_ip();
        elseif($this->is_valid_ip($options['ip']))
            $this->ip = $options['ip'];
        // кодировка
        if(isset($options['charset']) && $options['charset'] && $options['charset']!='windows-1251')
            $this->charset = $options['charset'];
    }


    /**
     * функция возвращет конкретное значение из полученного массива данных по ip
     * @param string - ключ массива. Если интересует конкретное значение.
     * Ключ может быть равным 'inetnum', 'country', 'city', 'region', 'district', 'lat', 'lng'
     * @param bolean - устанавливаем хранить данные в куки или нет
     * Если true, то в куки будут записаны данные по ip и повторные запросы на ipgeobase происходить не будут.
     * Если false, то данные постоянно будут запрашиваться с ipgeobase
     * @return array OR string - дополнительно читайте комментарии внутри функции.
     */
    function get_value($key = false, $cookie = true)
    {
        $key_array = array('inetnum', 'country', 'city', 'region', 'district', 'lat', 'lng');
        if(!in_array($key, $key_array))
            $key = false;

        // если используем куки и параметр уже получен, то достаем и возвращаем данные из куки
        if($cookie && isset($_COOKIE['geobase']))
        {
            $data = unserialize($_COOKIE['geobase']);
        }
        else
        {
            $data = $this->get_geobase_data();
            setcookie('geobase', serialize($data), time()+3600*24*7); //устанавливаем куки на неделю
        }
        if($key)
            return $data[$key]; // если указан ключ, возвращаем строку с нужными данными
        else
            return $data; // иначе возвращаем массив со всеми данными
    }

    /**
     * функция получает данные по ip.
     * @return array - возвращает массив с данными
     */
    function get_geobase_data()
    {
        // получаем данные по ip
        $link = 'http://ipgeobase.ru:7020/geo?ip='.$this->ip;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /*if (!defined('DISABLE_PROXY') || !DISABLE_PROXY) {
            curl_setopt($ch, CURLOPT_PROXY, 'http://fwproxy.vh.silverplate.ru:8080');
        }*/
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $string = curl_exec($ch);

        // если указана кодировка отличная от windows-1251, изменяем кодировку
        if($this->charset)
            $string = iconv('windows-1251', $this->charset, $string);

        $data = $this->parse_string($string);
        
        return $data;
    }

    /**
     * функция парсит полученные в XML данные в случае, если на сервере не установлено расширение Simplexml
     * @return array - возвращает массив с данными
     */

    function parse_string($string)
    {
        $pa['inetnum'] = '#<inetnum>(.*)</inetnum>#is';
        $pa['country'] = '#<country>(.*)</country>#is';
        $pa['city'] = '#<city>(.*)</city>#is';
        $pa['region'] = '#<region>(.*)</region>#is';
        $pa['district'] = '#<district>(.*)</district>#is';
        $pa['lat'] = '#<lat>(.*)</lat>#is';
        $pa['lng'] = '#<lng>(.*)</lng>#is';
        $data = array();
        foreach($pa as $key => $pattern)
        {
            if(preg_match($pattern, $string, $out))
            {
                $data[$key] = trim($out[1]);
            }
        }
        return $data;
    }

    /**
     * функция определяет ip адрес по глобальному массиву $_SERVER
     * ip адреса проверяются начиная с приоритетного, для определения возможного использования прокси
     * @return ip-адрес
     */
    function get_ip()
    {
        $ip = false;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipa[] = trim(strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ','));

        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipa[] = $_SERVER['HTTP_CLIENT_IP'];

        if (isset($_SERVER['REMOTE_ADDR']))
            $ipa[] = $_SERVER['REMOTE_ADDR'];

        if (isset($_SERVER['HTTP_X_REAL_IP']))
            $ipa[] = $_SERVER['HTTP_X_REAL_IP'];

        // проверяем ip-адреса на валидность начиная с приоритетного.
        foreach($ipa as $ips)
        {
            //  если ip валидный обрываем цикл, назначаем ip адрес и возвращаем его
            if($this->is_valid_ip($ips))
            {
                $ip = $ips;
                break;
            }
        }
        return $ip;

    }

    /**
     * функция для проверки валидности ip адреса
     * @param ip адрес в формате 1.2.3.4
     * @return bolean : true - если ip валидный, иначе false
     */
    function is_valid_ip($ip=null)
    {
        if(preg_match("#^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$#", $ip))
            return true; // если ip-адрес попадает под регулярное выражение, возвращаем true

        return false; // иначе возвращаем false
    }
}
function getFoundCity()
{
    $cityArr = array();
    $cityArr = getCityArr();

    if (!empty($cityArr)) {
        if(
            !isset($_COOKIE['client-city-id']) ||
            empty($_COOKIE['client-city-id']) ||
            ($_COOKIE['client-city-id'] == 'undefined')
        ) {
            $foundCity = checkCityID($cityArr);

            $res = CIBlockSection::GetByID($foundCity['sectionId']);

            if($ar_res = $res->GetNext()) {
                $foundCity['region'] = regionEnd($ar_res['NAME']);
            }

            // $_COOKIE['client-city-id'] = $foundCity['id'];
            $_COOKIE['client-city-name'] = $foundCity['name'];
            if ($foundCity['salon'] == 'no') {
                $_salonCity = findClosestSalon($cityArr, $foundCity['id']);
                $foundCity['id_salon_town'] = $_salonCity['id_salon_town'];
                $foundCity['name_salon_town'] = $_salonCity['name_salon_town'];
            } else {
                $foundCity['id_salon_town'] = 0;
                $foundCity['name_salon_town'] = 0;
            }
            // echo json_encode($foundCity);
        } else {
            $foundCity = $cityArr[$_COOKIE['client-city-id']];

            $res = CIBlockSection::GetByID($foundCity['sectionId']);

            if($ar_res = $res->GetNext()) {
                $foundCity['region'] = regionEnd($ar_res['NAME']);
            }

            if ($foundCity['salon'] == 'no') {
                $_salonCity = findClosestSalon($cityArr, $foundCity['id']);
                $foundCity['id_salon_town'] = $_salonCity['id_salon_town'];
                $foundCity['name_salon_town'] = $_salonCity['name_salon_town'];
            } else {
                $foundCity['id_salon_town'] = 0;
                $foundCity['name_salon_town'] = 0;
            }
            // echo json_encode($foundCity);
        }

        if ($foundCity['distance'] !== 0) {
            $closestDelivery = findClosestDelivery($cityArr, $foundCity['id']);
            /*$foundCity['salon'] = $closestDelivery['salon'];
            $foundCity['in'] = $closestDelivery['in'];
            $foundCity['up'] = $closestDelivery['up'];
            $foundCity['assembly_2'] = $closestDelivery['assembly_2'];
            $foundCity['assembly_3'] = $closestDelivery['assembly_3'];
            $foundCity['assembly_corner'] = $closestDelivery['assembly_corner'];*/
            $foundCity['delivery'] = $closestDelivery['delivery'];
            $foundCity['closest_delivery'] = $closestDelivery['closest_delivery'];
            $foundCity['closest_delivery_town'] = $closestDelivery['name'];
        } else {
            $foundCity['closest_delivery'] = 0;
            $foundCity['closest_delivery_town'] = '';
        }

        return $foundCity;
    }
    else
        return array();
}

// получение городов заведенных в админке
function getCityArr()
{
    CModule::IncludeModule('iblock');
    $cityArr = array();

    $arElememtFilter = array(
        'IBLOCK_ID'=>IBLOCK_CITY,
        'ACTIVE'=>'Y'
    );

    $cities = CIBlockElement::GetList(
        array(
            "NAME"=>"ASC"
        ),
        $arElememtFilter,
        false,
        false,
        array('*','PROPERTY_REGION.PROPERTY_DELIVERY_TIME')
    );

    while($city = $cities->GetNextElement()) {
        $arFields = $city->GetFields();
        $arProps = $city->GetProperties();

        $cityArr[$arFields['ID']] = array(
            'id' => $arFields['ID'],
            'sectionId' => $arFields['IBLOCK_SECTION_ID'],
            'name' => $arFields['NAME'],
            'phone' => $arProps['PHONE']['VALUE'],
            'longitude' => $arProps['LON']['VALUE'],
            'latitude' => $arProps['LAT']['VALUE'],
            'salon' => $arProps['SALON']['VALUE_XML_ID'],
            'delivery' => $arProps['DELIVERY']['VALUE'],
            'distance' => $arProps['DISTANCE']['VALUE'],
            'in' => $arProps['IN']['VALUE'],
            'up' => $arProps['UP']['VALUE'],
            'assembly_meassure' => $arProps['ASSEMBLY_MEASSURE']['VALUE_XML_ID'],
            'assembly_percent' => $arProps['ASSEMBLY_PERCENT']['VALUE'],
            'assembly_2' => $arProps['ASSEMBLY_2']['VALUE'],
            'assembly_3' => $arProps['ASSEMBLY_3']['VALUE'],
            'assembly_corner' => $arProps['ASSEMBLY_CORNER']['VALUE'],
            'assembly_esta' => $arProps['COST_ASSEMBLY_ESTA']['VALUE'],
            'assembly_optim' => $arProps['COST_ASSEMBLY_OPTIM']['VALUE'],
            'assembly_express' => $arProps['COST_ASSEMBLY_EXPRESS']['VALUE'],
            'cost_assembly_corner' => $arProps['COST_ASSEMBLY_MODULE']['VALUE'],
            'delivery_time_region' => $arFields['PROPERTY_REGION_PROPERTY_DELIVERY_TIME_VALUE']
        );
    }
    
    return $cityArr;
}

function findClosestSalon($_cities = array(), $_idCity = 0)
{
    if ($_idCity != 0) {
        $loc = array(
            'longitude' => $_cities[$_idCity]['longitude'],
            'latitude' => $_cities[$_idCity]['latitude'],
        );

        if (!empty($loc)) {

            // ищем ближайший из списка город в местоположению посетителя сайта

            $dist = 100000;
            $isCity = '';
            $isCityName = '';

            // инфо о местоположении посетителя

            $longitudeG = $loc['longitude'];
            $latitudeG = $loc['latitude'];

            // переводим в радианы

            $lat1 = $latitudeG * pi() / 180;
            $long1 = $longitudeG * pi() / 180;

            if ($longitudeG != 0 && $latitudeG != 0)
                foreach ($_cities as $city) {
                    if ($city['salon'] == 'yes') {
                        $lat2 = $city['latitude'] * pi() / 180;
                        $long2 = $city['longitude'] * pi() / 180;


                        // косинусы и синусы широт и разницы долгот

                        $cl1 = cos($lat1);
                        $cl2 = cos($lat2);
                        $sl1 = sin($lat1);
                        $sl2 = sin($lat2);
                        $delta = $long2 - $long1;
                        $cdelta = cos($delta);
                        $sdelta = sin($delta);

                        // вычисления длины большого круга

                        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
                        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

                        //
                        $ad = atan2($y, $x);
                        $dist1 = $ad * 6371;

                        if ($dist1 < $dist) {
                            $isCity = $city['id'];
                            $isCityName = $city['name'];
                            $dist = $dist1;
                        }
                    }
                }

            if ($isCity != '') {
                $cityName['id_salon_town'] = $isCity;
                $cityName['name_salon_town'] = $isCityName;
            } else {
                $cityName['id_salon_town'] = 0;
                $cityName['name_salon_town'] = '';
            }
        } else {
            $cityName['id_salon_town'] = 0;
            $cityName['name_salon_town'] = '';
        }
    } else {
        $cityName['id_salon_town'] = 0;
        $cityName['name_salon_town'] = '';
    }

    return $cityName;
}

function findClosestDelivery($_cities = array(), $_idCity = 0)
{
    if ($_idCity != 0) {
        $loc = array(
            'longitude' => $_cities[$_idCity]['longitude'],
            'latitude' => $_cities[$_idCity]['latitude'],
        );

        if (!empty($loc)) {

            // ищем ближайший из списка город в местоположению посетителя сайта

            $dist = 100000;
            $isCity = '';
            $isCityName = '';

            // инфо о местоположении посетителя

            $longitudeG = $loc['longitude'];
            $latitudeG = $loc['latitude'];

            // переводим в радианы

            $lat1 = $latitudeG * pi() / 180;
            $long1 = $longitudeG * pi() / 180;

            if ($longitudeG != 0 && $latitudeG != 0)
                foreach ($_cities as $city) {
                    if ($city['distance'] == '0') {
                        $lat2 = $city['latitude'] * pi() / 180;
                        $long2 = $city['longitude'] * pi() / 180;


                        // косинусы и синусы широт и разницы долгот

                        $cl1 = cos($lat1);
                        $cl2 = cos($lat2);
                        $sl1 = sin($lat1);
                        $sl2 = sin($lat2);
                        $delta = $long2 - $long1;
                        $cdelta = cos($delta);
                        $sdelta = sin($delta);

                        // вычисления длины большого круга

                        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
                        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

                        //
                        $ad = atan2($y, $x);
                        $dist1 = $ad * 6371;

                        if ($dist1 < $dist) {
                            $isCity = $city['id'];
                            // $distance = $dist1;
                            $isCityName = $city['name'];
                            $dist = $dist1;
                        }
                    }
                }

            if ($isCity != '') {
                $cityName['name'] = $isCityName;
                // $cityName['distance'] = $distance;
                $cityName['delivery'] = $_cities[$isCity]['delivery'];
                /*$cityName['salon'] = $_cities[$isCity]['salon'];
                $cityName['delivery'] = $_cities[$isCity]['delivery'];
                $cityName['in'] = $_cities[$isCity]['in'];
                $cityName['up'] = $_cities[$isCity]['up'];
                $cityName['assembly_2'] = $_cities[$isCity]['assembly_2'];
                $cityName['assembly_3'] = $_cities[$isCity]['assembly_3'];
                $cityName['assembly_corner'] = $_cities[$isCity]['assembly_corner'];*/
            } else {
                $cityName['distance'] = '';
                $cityName['delivery'] = 0;
                /*$cityName['salon'] = '';
                $cityName['in'] = 0;
                $cityName['up'] = 0;
                $cityName['assembly_2'] = 0;
                $cityName['assembly_3'] = 0;
                $cityName['assembly_corner'] = 0;*/
            }
        } else {
            $cityName['distance'] = '';
            $cityName['delivery'] = 0;
            /*$cityName['salon'] = '';
            $cityName['delivery'] = 0;
            $cityName['in'] = 0;
            $cityName['up'] = 0;
            $cityName['assembly_2'] = 0;
            $cityName['assembly_3'] = 0;
            $cityName['assembly_corner'] = 0;*/
        }
    } else {
        $cityName['distance'] = '';
        $cityName['delivery'] = 0;
        /*$cityName['salon'] = '';
        $cityName['delivery'] = 0;
        $cityName['in'] = 0;
        $cityName['up'] = 0;
        $cityName['assembly_2'] = 0;
        $cityName['assembly_3'] = 0;
        $cityName['assembly_corner'] = 0;*/
    }

    $cityName['closest_delivery'] = 1;

    return $cityName;
}

function checkCityID($_cities = array())
{
    if(
        !isset($_COOKIE['client-city-id']) || ($_COOKIE['client-city-id'] == 'undefined')
    ) {
        if (isset($GLOBALS['gWin']))
            return $GLOBALS['gWin'];
        else {
            if (isset($GLOBALS['gIp'])) {
                $ip = $GLOBALS['gIp'];
                $loc = NULL;
            } else
                $ip = $loc = NULL;

            //  Определяем IP адрес пользователя.
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ips = explode(',', $_SERVER['HTTP_X_REAL_IP']);
                $ip = $ips[0];
            }
            else if (!empty($_SERVER['REMOTE_ADDR']))
                $ip = $_SERVER['REMOTE_ADDR'];

            //  Если IP определили, то ищем расположение.
            if (!empty($ip) && $ip != '127.0.0.1') {
                $o = array(); // опции. необзятательно.
                $o['charset'] = 'utf-8'; // нужно указать требуемую кодировку, если она отличается от windows-1251
                $o['ip'] = $ip;

                $geo = new Geo($o); // запускаем класс
                $geo_data = $geo->get_geobase_data();
                if (!isset($geo_data['lat']) || !isset($geo_data['lng']))
                    // нет в базе ipgeobase.ru скорее всего это не РФ и не Украина и тогда отрабатывает GeoIp
                    if (function_exists(geoip_record_by_name)) {
                        if (!$loc = geoip_record_by_name($ip)) {
                            //Ничего не найдено
                            $loc = array(
                                'longitude' => 44.791396,
                                'latitude' => 38.749057,
                            );
                        }
                    } else {
                        $loc = array(
                            'longitude' => 44.791396,
                            'latitude' => 38.749057,
                        );
                    }
                else
                    $loc = array(
                        'longitude' => $geo_data['lng'],
                        'latitude' => $geo_data['lat'],
                    );
            } else if ($ip == '127.0.0.1')
                // для оплеределиня на локалках, если убрать,
                // будет выдаваться сообщение о неудаче в определении местоположения
                $loc = array(
                    'longitude' => 44.791396,
                    'latitude' => 38.749057,
                );

            //  Если расположение определено, то определяем и задаем город
            if (!empty($loc)) {

                // ищем ближайший из списка город в местоположению посетителя сайта

                $dist = 100000;
                $isCity = '';
                $isCityName = '';

                // инфо о местоположении посетителя

                $longitudeG = $loc['longitude'];
                $latitudeG = $loc['latitude'];

                // переводим в радианы

                $lat1 = $latitudeG * pi() / 180;
                $long1 = $longitudeG * pi() / 180;

                if ($longitudeG != 0 && $latitudeG != 0)
                    foreach ($_cities as $city) {
                        $lat2 = $city['latitude'] * pi() / 180;
                        $long2 = $city['longitude'] * pi() / 180;


                        // косинусы и синусы широт и разницы долгот

                        $cl1 = cos($lat1);
                        $cl2 = cos($lat2);
                        $sl1 = sin($lat1);
                        $sl2 = sin($lat2);
                        $delta = $long2 - $long1;
                        $cdelta = cos($delta);
                        $sdelta = sin($delta);

                        // вычисления длины большого круга

                        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
                        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

                        //
                        $ad = atan2($y, $x);
                        $dist1 = $ad * 6371;

                        if ($dist1 < $dist) {
                            $isCity = $city['id'];
                            $isCityName = $city['name'];
                            $dist = $dist1;
                        }
                    }
                if ($isCity != '') {
                    $cityName['ip'] = $ip;
                    $cityName['id'] = $isCity;
                    $cityName['name'] = $isCityName;
                    $cityName['phone'] = $_cities[$isCity]['phone'];
                    $cityName['sectionId'] = $_cities[$isCity]['sectionId'];
                    $cityName['salon'] = $_cities[$isCity]['salon'];
                    $cityName['delivery'] = $_cities[$isCity]['delivery'];
                    $cityName['distance'] = $_cities[$isCity]['distance'];
                    $cityName['in'] = $_cities[$isCity]['in'];
                    $cityName['up'] = $_cities[$isCity]['up'];
                    $cityName['assembly_2'] = $_cities[$isCity]['assembly_2'];
                    $cityName['assembly_3'] = $_cities[$isCity]['assembly_3'];
                    $cityName['assembly_corner'] = $_cities[$isCity]['assembly_corner'];
                    $cityName['cost_assembly_corner'] = $_cities[$isCity]['cost_assembly_corner'];
                } else {
                    $cityName['id'] = 0;
                    $cityName['sectionId'] = 0;
                    $cityName['name'] = '';
                    $cityName['salon'] = '';
                    $cityName['delivery'] = 0;
                    $cityName['distance'] = '';
                    $cityName['in'] = 0;
                    $cityName['up'] = 0;
                    $cityName['assembly_2'] = 0;
                    $cityName['assembly_3'] = 0;
                    $cityName['assembly_corner'] = 0;
                    $cityName['cost_assembly_corner'] = 0;
                }
            } else {
                $cityName['id'] = 0;
                $cityName['sectionId'] = 0;
                $cityName['name'] = '';
                $cityName['salon'] = '';
                $cityName['delivery'] = 0;
                $cityName['distance'] = '';
                $cityName['in'] = 0;
                $cityName['up'] = 0;
                $cityName['assembly_2'] = 0;
                $cityName['assembly_3'] = 0;
                $cityName['assembly_corner'] = 0;
                $cityName['cost_assembly_corner'] = 0;
            }
        }
    } else {
        $cityName['id'] = $_COOKIE['client-city-id'];
        $cityName['name'] = $_COOKIE['client-city-name'];
        $cityName['phone'] = $_cities[$_COOKIE['client-city-id']]['phone'];
        $cityName['sectionId'] = $_cities[$_COOKIE['client-city-id']]['sectionId'];
        $cityName['salon'] = $_cities[$_COOKIE['client-city-id']]['salon'];
        $cityName['delivery'] = $_cities[$_COOKIE['client-city-id']]['delivery'];
        $cityName['distance'] = $_cities[$_COOKIE['client-city-id']]['distance'];
        $cityName['in'] = $_cities[$_COOKIE['client-city-id']]['in'];
        $cityName['up'] = $_cities[$_COOKIE['client-city-id']]['up'];
        $cityName['assembly_2'] = $_cities[$_COOKIE['client-city-id']]['assembly_2'];
        $cityName['assembly_3'] = $_cities[$_COOKIE['client-city-id']]['assembly_3'];
        $cityName['assembly_corner'] = $_cities[$_COOKIE['client-city-id']]['assembly_corner'];
        $cityName['cost_assembly_corner'] = $_cities[$_COOKIE['client-city-id']]['cost_assembly_corner'];
    }

    return $cityName;
}

function regionEnd ($region) {
    $regions = array(
        'Алтайский край' => 'городам Алтайского края',
        'Амурская область' => 'городам Амурской области',
        'Архангельская область' => 'городам Архангельской области',
        'Астраханская область' => 'городам Астраханской области',
        'Белгородская область' => 'городам Белгородской области',
        'Брянская область' => 'городам Брянской области',
        'Владимирская область' => 'городам Владимирской области',
        'Волгоградская область' => 'городам Волгоградской области',
        'Вологодская область' => 'городам Вологодской области',
        'Воронежская область' => 'городам Воронежской области',
        'Москва' => 'Москве',
        'Еврейская автономная область' => 'городам Еврейской автономной области',
        'Забайкальский край' => 'городам Забайкальского края',
        'Ивановская область' => 'городам Ивановской области',
        'Байконур' => 'Байконуру',
        'Иркутская область' => 'городам Иркутской области',
        'Республика Кабардино-Балкария' => 'городам Республики Кабардино-Балкария',
        'Калининградская область' => 'городам Калининградской области',
        'Калужская область' => 'городам Калужской области',
        'Камчатский край' => 'городам Камчатского края',
        'Республика Карачаево-Черкессия' => 'городам Республики Карачаево-Черкессия',
        'Кемеровская область' => 'городам Кемеровской области',
        'Кировская область' => 'городам Кировской области',
        'Костромская область' => 'городам Костромской области',
        'Краснодарский край' => 'городам Краснодарского края',
        'Красноярский край' => 'городам Красноярского края',
        'Курганская область' => 'городам Курганской области',
        'Курская область' => 'городам Курской области',
        'Ленинградская область' => 'городам Ленинградской области',
        'Липецкая область' => 'городам Липецкой области',
        'Магаданская область' => 'городам Магаданской области',
        'Московская область' => 'городам Московской области',
        'Мурманская область' => 'городам Мурманской области',
        'Ненецкий автономный округ' => 'городам Ненецкого автономного округа',
        'Нижегородская область' => 'городам Нижегородской области',
        'Новгородская область' => 'городам Новгородской области',
        'Новосибирская область' => 'городам Новосибирской области',
        'Омская область' => 'городам Омской области',
        'Оренбургская область' => 'городам Оренбургской области',
        'Орловская область' => 'городам Орловской области',
        'Пензенская область' => 'городам Пензенской области',
        'Пермский край' => 'городам Пермского края',
        'Приморский край' => 'городам Приморского края',
        'Псковская область' => 'городам Псковской области',
        'Республика Адыгея' => 'городам Республики Адыгея',
        'Республика Алтай' => 'городам Республики Алтай',
        'Республика Башкортостан' => 'городам Республики Башкортостан',
        'Республика Бурятия' => 'городам Республики Бурятия',
        'Республика Дагестан' => 'городам Республики Дагестан',
        'Республика Ингушетия' => 'городам Республики Ингушетия',
        'Республика Калмыкия' => 'городам Республики Калмыкия',
        'Республика Карелия' => 'городам Республики Карелия',
        'Республика Коми' => 'городам Республики Коми',
        'Республика Крым' => 'городам Республики Крым',
        'Республика Марий Эл' => 'городам Республики Марий Эл',
        'Республика Мордовия' => 'городам Республики Мордовия',
        'Республика Саха' => 'городам Республики Саха',
        'Республика Северная Осетия' => 'городам Республики Северная Осетия',
        'Республика Татарстан' => 'городам Республики Татарстан',
        'Республика Тыва' => 'городам Республики Тыва',
        'Республика Хакасия' => 'городам Республики Хакасия',
        'Ростовская область' => 'городам Ростовской области',
        'Рязанская область' => 'городам Рязанской области',
        'Самарская область' => 'городам Самарской области',
        'Санкт-Петербург' => 'Санкт-Петербургу',
        'Саратовская область' => 'городам Саратовской области',
        'Сахалинская область' => 'городам Сахалинской области',
        'Свердловская область' => 'городам Свердловской области',
        'Севастополь' => 'Севастополю',
        'Смоленская область' => 'городам Смоленской области',
        'Ставропольский край' => 'городам Ставропольского края',
        'Тамбовская область' => 'городам Тамбовской области',
        'Тверская область' => 'городам Тверской области',
        'Томская область' => 'городам Томской области',
        'Тульская область' => 'городам Тульской области',
        'Тюменская область' => 'городам Тюменской области',
        'Удмуртская Республика' => 'городам Удмуртской Республики',
        'Ульяновская область' => 'городам Ульяновской области',
        'Хабаровский край' => 'городам Хабаровского края',
        'Ханты-Мансийский автономный округ' => 'городам Ханты-Мансийского автономного округа',
        'Челябинская область' => 'городам Челябинской области',
        'Чеченская Республика' => 'городам Чеченской Республики',
        'Республика Чувашия' => 'городам Республики Чувашия',
        'Чукотский автономный округ' => 'городам Чукотского автономного окруа',
        'Ямало-Ненецкий автономный округ' => 'городам Ямало-Ненецкого автономного округа',
        'Ярославская область' => 'городам Ярославского области',
    );

    if (isset($regions[$region])) {
        return $regions[$region];
    } else {
        return 'городам Урюпинского края';
    }
}
echo $cityName;
?>