
--------------------------------------
сайт-визитка ООО "Средства Информации"
--------------------------------------

Шаблонизатор, настраивается в ini.php

// 2. Выборка сотрудников
/*
 * реквизиты фирмы:
select * from tclient tc where tc.idclient=63920;

 * контакты фирмы:
select lov.iidlnkvidconnect, lov.iidvidconnect, lov.blive2, tc.strclient,
 tp.*, te.*, tw.*
from tclient tc
join lnk_object_vidconnect lov ON lov.iidlnkobject = tc.idclient AND lov.iidobject=6 AND lov.blive2=1
left join tbltelefon tp ON tp.idtelefon = lov.iidlnkvidconnect AND lov.iidvidconnect = 1 AND tp.e164 NOT LIKE '79%'
left join tblemail   te ON te.idemail   = lov.iidlnkvidconnect AND lov.iidvidconnect = 2
left join tblweb     tw ON tw.idweb     = lov.iidlnkvidconnect AND lov.iidvidconnect = 3
where tc.blive=1 AND tc.bour=1 AND ((tp.idtelefon IS NOT NULL) OR (te.idemail IS NOT NULL) OR (tw.idweb IS NOT NULL))
;

 * сотрудники фирмы:
select tc.strclient, tm.*
from tclient tc
join lnk_manager2client lm ON lm.iidclient = tc.idclient and lm.blive=1
join tblmanager tm ON tm.idmanager = lm.iidmanager and tm.dout IS NULL
where tc.blive=1 and tc.bour=1 # tc.idclient=63920
;

 * крупные клиенты:
select sc.strclient, sc.idclient, cc.strinn,
       group_concat(distinct cc.strclient SEPARATOR "#"),
       group_concat(distinct cf.strbrendname SEPARATOR '#'),
       count(p.`idplatez`) as cnt,
       sum(p.`fsumma`) as _sum,
       min(p.dplatez) as dtstart
from
    tplatez p
        inner join tclient cc on cc.idclient = p.iidcustomerclient
        inner join tclient sc on sc.idclient = p.iidsupplierclient AND sc.idclient = 63920
        inner join tschet s   on s.idschet = p.iidschet
        inner join tblfirm cf on cf.`idfirm` = s.iidcustomerfirm
where p.dplatez >= '2017-01-01' and cc.bour != 1 and sc.bour=1 and length(cc.strinn) > 0
  and isnull(p.iidzayavka)
group by sc.idclient, cc.idclient
# having _sum > 40000
order by _sum desc
limit 20;

 * регионы:
SELECT DISTINCT idregion,strnameregion
FROM
    tblregion
    inner join tblgorod on iidregion=idregion
    inner join tblsmi on iidgorod=idgorod and blive=1 and bvisible=1
WHERE bregion=1 and bforeign = 2
ORDER BY strnameregion";

 * города
$gorod = TGorod::Proxy()->load_id($this->_getParam('city_id', 0));
$region = $gorod->getRegion();
$okrug = $region->getRegionBig();
