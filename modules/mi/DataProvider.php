<?php

use utils\MySql;

/**
 * Class DataProvider -- выборки из СУБД данных по сайту.
 *
 * Сборка всех SQL запросов в одном месте .. типа "модель" MVC
 *
 * @global int $idClient -- идент фирмы в rotor::tclient
 *
 * @author fvn20190417..
 */
class DataProvider
{
  /** @var MySql $db -- адаптер к СУБД */
  public static $db;
  
  /**
   * @return array
   * @throws Exception
   */
  public function &getRekvisits()
  {
    global $idClient;

    $rows = static::$db->selectAll('select * from tclient tc where tc.blive=1 AND tc.idclient=?;', [$idClient]);
    if( empty($rows) ){ self::$db->debugger->raise('ERROR! Нет данных по фирме .. скончалась?'); }
    $row = $rows[0];

    return $row;
  }
  
  /**
   * Получить данные по округу, региону(округа), городу(региона)
   *
   * @param string $detail -- {'big'|'reg'|'city'}
   * @param int    $fkey
   * @return array[id, strName, strDescription]
   */
  public function &getRegions( $detail = 'big', $fkey=0)
  {
    global $idClient;

    switch($detail){
      case 'big':
        $rows = static::$db->selectAll(
          'select tb.idbigregion AS id, tb.strbigregion_alias AS strName, tb.strdescription AS strDescription from tblbigregion tb;'
        );
        break;
      case 'reg':
        $rows = static::$db->selectAll(
          'select tr.idregion AS id, tr.strnameregion AS strName, tr.strdetailregion AS strDescription
from tblregion tr
join tblregiontobigregion tl ON tl.iidregion = tr.idregion AND tl.iidbigregion = ?
;'
          ,[ (int)$fkey ]
        );
        break;
      case 'city':
        $rows = static::$db->selectAll(
          'select tg.idgorod AS id, tg.strgorod AS strName,
CONCAT_WS("\n<br>", CONCAT("Население: ", tg.inaselenie, " чел."), tg.stropisanie) AS strDescription
from tblgorod tg
where tg.iidregion = ?;'
        , [ (int)$fkey ]);
        break;
      default:
        $rows = ['id'=>-1, 'strName'=>'что это?', 'strDescription'=>'непонятный тип выборки DataProvider::getRegions()'];
    }
    return $rows;
  }

  public function &getContacts()
  {
    global $idClient;

    $rows = static::$db->selectAll(
      'select
  if( tp.idtelefon IS NOT NULL, "тел-н.",
    if( te.idemail IS NOT NULL, "эл. адрес",
      if( tw.idweb IS NOT NULL, "сайт", NULL)
    )
  ) as type,
  if( tp.idtelefon IS NOT NULL, tp.strtelefon,
    if( te.idemail IS NOT NULL, te.stremail,
      if( tw.idweb IS NOT NULL, tw.strweb, NULL)
    )
  ) as contact
from tclient tc
join lnk_object_vidconnect lov ON lov.iidlnkobject = tc.idclient AND lov.iidobject=6 AND lov.blive2=1
left join tbltelefon tp ON tp.idtelefon = lov.iidlnkvidconnect AND lov.iidvidconnect = 1 AND tp.e164 NOT LIKE \'79%\'
left join tblemail   te ON te.idemail   = lov.iidlnkvidconnect AND lov.iidvidconnect = 2
left join tblweb     tw ON tw.idweb     = lov.iidlnkvidconnect AND lov.iidvidconnect = 3
where tc.blive=1 AND tc.bour=1 AND ((tp.idtelefon IS NOT NULL) OR (te.idemail IS NOT NULL) OR (tw.idweb IS NOT NULL))
  AND tc.idclient = ?
;'
      , [$idClient]
    );
    return $rows;
  }

  public function getManagers()
  {
    global $idClient;

    $rows = static::$db->selectAll('
select tc.strclient, tm.*
from tclient tc
join lnk_manager2client lm ON lm.iidclient = tc.idclient and lm.blive=1
join tblmanager tm ON tm.idmanager = lm.iidmanager and tm.dout IS NULL
where tc.blive=1 and tc.bour=1 and tc.idclient=?
;'
      ,[ $idClient ]
    );
    return $rows;
  }

  public function getPartners()
  {
    global $idClient;
    
    $rows = static::$db->selectAll('
select
   cc.strinn AS pINN,
   group_concat(distinct cc.strclient SEPARATOR ",") AS pName,
   group_concat(distinct cf.strbrendname SEPARATOR ",") AS pBrend,
   count(p.`idplatez`) as cnt,
   sum(p.`fsumma`) as fsum,
   min(p.dplatez) as dtStart
from
    tplatez p
        inner join tclient cc on cc.idclient = p.iidcustomerclient
        inner join tclient sc on sc.idclient = p.iidsupplierclient AND sc.idclient = ?
        inner join tschet s   on s.idschet = p.iidschet
        inner join tblfirm cf on cf.`idfirm` = s.iidcustomerfirm
where p.dplatez >= "2017-01-01" and cc.bour != 1 and sc.bour=1 and length(cc.strinn) > 0
  and isnull(p.iidzayavka)
group by sc.idclient, cc.idclient
# having fsum > 40000
order by fsum desc
limit 20;'
      , [$idClient]
    );
    return $rows;
  }

  /** печатные издания */
  public function getSmi($idRegion, $idCity=0)
  {
    global $idClient;

    if( $idCity == 0 ){
      // Поиск печатки по региону через таблицу связи
      $rows = static::$db->selectAll('
SELECT "smi" AS type, sm.idsmi AS id, sm.strnazvanie AS strName, sm.itiraz AS itiraz, sm.strcomment AS strComment
# CONCAT_WS("\n<br>", sm.strcomment, sm.strprim) AS strComment
FROM tblsmi AS sm
JOIN tblsmiregion AS sr ON sr.iidregion=? AND sr.iidsmi = sm.idsmi
WHERE sm.blive=1 AND sm.bvisible=1
;
      ', [$idRegion]);
    }else{
      // Поиск Печатки по городу напрямую
      $rows = static::$db->selectAll('
SELECT "smi" AS type, sm.idsmi AS id, sm.strnazvanie AS strName, sm.itiraz AS itiraz, sm.strcomment AS strComment
# CONCAT_WS("\n<br>", sm.strcomment, sm.strprim) AS strComment
FROM tblsmi AS sm
WHERE sm.iidgorod = ? AND sm.blive=1 AND sm.bvisible=1
      ',[$idCity]);
    }
    return $rows;
  }

  /** радиостанции: отдает пустой если нет города - нет региональной привязки! */
  public function getRadio($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
SELECT "radio" AS type, sm.idradio AS id, sm.strradio AS strName, sm.strchastota AS strchastota, sm.stropisanie AS strComment
FROM tblradio AS sm
WHERE sm.iidgorod = ? AND sm.blive=1
      ', [$idCity]);
    }
    return $rows;
  }

  /** аналогично радио: нет региональной привязки. Только к городу. */
  public function getTV($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
SELECT "tv" AS type, sm.idtv AS id, CONCAT_WS(", ", sm.strtv, sm.ikanal) AS strName, sm.stropisanie AS strComment
FROM tbltv AS sm
WHERE sm.iidgorod = ? AND sm.blive=1 AND sm.bvisible=1
      ', [$idCity]);
    }
    return $rows;
  }

  const OUR_FIRM = 1;
  const PRICE_TRANSPORT = 425;
  const TEMPLATE_METRO     = 1;
  const TEMPLATE_TRANSPORT = 2;

  /** transport */
  public function getTransport($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
select "cars" AS type, `pi`.`idprice_item` AS id, `pi`.`strname` AS strName
  , CONCAT("Цена: ", `pi`.`fprice`, "руб., кол-во:", `pi`.`fquantity`, "шт., период: ", `pim`.`strperiod`) AS strComment
  , IFNULL(`pi`.`strcomment`, "{}") as json
from `tprice` `p`
 join `tprice_item` `pi` on `p`.`idprice` = `pi`.`iidprice`
 join `tprice_item_transport` `pim` on `pim`.`iidprice_item` = `pi`.`idprice_item`
where `p`.`iidfirm` = 1 and `p`.`blive` = 1 and `p`.`iidprice_template` = 2 and `p`.`iidparent` = 425 and p.iidgorod = ?
;
      ', [$idCity]);
      if( !empty($rows) )
        foreach($rows AS &$r){
          if( $r['json'] != '{}' ){
            $json = unserialize($r['json']);
//die(var_dump($json));
            $r['strComment'] .= ', тираж: '.$json['tirage'];
          }
        }
    }
    return $rows;
  }

  /** metro */
  public function getMetro($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
select "metro" AS type, pi.idprice_item AS id, pi.strname AS strName
  , CONCAT("Цена: ", pi.fprice, "руб., Кол-во: ", pi.fquantity, "шт., период: ", pim.strperiod, ", размер: ", pim.strsize) AS strComment
  , IFNULL(pi.strcomment, "{}") as json
from `tprice` `p`
  join `tprice_item` `pi` on `p`.`idprice` = `pi`.`iidprice`
  join `tprice_item_metro` `pim` on `pim`.`iidprice_item` = `pi`.`idprice_item`
where `p`.`iidfirm` = 1 and `p`.`blive` = 1 and `p`.`iidprice_template` = 1 and `p`.`iidgorod` = ?
;
      ', [$idCity]);
    }
    if( !empty($rows) )
      foreach($rows AS &$r){
        if( $r['json'] != '{}' ){
          $r['strComment'] .= ', прим.: '.$r['json'];
        }
      }
    return $rows;
  }

  /** outdoor */
  public function getOutdoor($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
select "bild" AS type, b.`type_id` AS id, CONCAT(bt.`strname`, " (", COUNT(0), "шт.)") AS strName, bt.description AS strComment
from `outdoor`.`build` AS b
join `outdoor`.`build_types` AS bt ON b.type_id = bt.alias_id
where b.alive=1 and b.city_id = ?
group by b.`type_id`
;
      ', [$idCity]);
    }
    return $rows;
  }

  public static $promoNames = [
    'fprice1' => 'Промо акция №1',
    'fprice2' => 'Промо акция №2',
    'fprice3' => 'Промо акция №3',
    'fprice4' => 'Промо акция №4',
    'fprice5' => 'Промо акция №5',
    'fprice6' => 'Промо акция №6',
    'fprice7' => 'Промо акция №7'
  ];

  /** promo (btl) - если есть, то одной записью!! */
  public function getPromo($idRegion, $idCity=0)
  {
    global $idClient;

    $res = $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
select `p`.* from `tprice_promo` `p` where `p`.iidcity = ?;
      ', [$idCity]
      );
      if( !empty($rows) ){
        foreach($rows[0] as $key=>$val){
          if( $key == 'idprice_promo' || $key=='iidcity' || $key == 'fake'){ continue; }
          $res[] = ['type'=>'btl', 'id'=> $key, 'strName'=>static::$promoNames[$key] . ' ('.$val.' руб.)', 'strComment'=>''];
        }
      }
    }
//die(var_dump($res, $rows));
    return $res;
  }

  /** elevators */
  public function getLifts($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
select "lift" AS type, e.id AS id, CONCAT(e.separation, " (", e.count, "шт.)") AS strName, GROUP_CONCAT( DISTINCT em.size ) AS strComment
from `elevators`.`blocks` AS e
join `elevators`.`modules` AS em ON e.id = em.block_id
where e.`city_id` = ?
group by e.id
;
      ', [$idCity]);
    }
    return $rows;
  }

  /** internet */
  public function getInternet($idRegion, $idCity=0)
  {
    global $idClient;

    $rows = [];
    if( $idCity>0 ){
      $rows = static::$db->selectAll('
select "inet" AS type, s.iidsite AS id, s.strsitename AS strName, s.strdesc AS strComment
from `tsite` `s`
left join `tsite_stat_gorod` `t` on `t`.`isiteid` = `s`.`iidsite` and `t`.`barchive` = 0 and `t`.`igorod` > 0 and `t`.`ikolvo` * 7 > 10000
left join `tsite_stat_gorod_xref` `x` on `x`.`idsite` = `s`.`iidsite` and `x`.`idgorod` = `t`.`igorod`
where (`s`.`idgorod` = ? or `t`.`igorod` = ? and `x`.`idgorod` is null) and `s`.`blive` = 1
group by s.iidsite
order by s.strsitename
;      ', [$idCity, $idCity]);
    }
    return $rows;
  }

  public function __construct(MySql $pdo){ static::$db = $pdo; }
}