`git clone https://github.com/SbWereWolf/extract-fias-params.git .`

# Инструкция как из ФИАС достать почтовые индексы всех городов

Выгрузка почтовых индексов для всех населённых пунктов, состоит из 
нескольких этапов:

1. Выгрузить данные с сайта ФНС
2. Подготовим СУБД для импорта данных ФИАС
3. Подготовка PHP скриптов для импорта
4. Импортировать данные в БД
5. Сформировать выгрузку

## Требования к обеспечению

Что нам понадобиться

- 1Tb file storage
- PostgreSQL || MySQL
- PHP
- Composer
- Unzip

Для работы PHP скриптов понадобятся следующие расширения:
```shell
apt-get install php
apt-get install php-xml
# если в качестве СУБД выбираем PostgreSQL
apt-get install php-pgsql
# если в качестве СУБД выбираем MySQL
apt-get install php-mysql
```
Ниже будет приведён пример для Ubuntu 24 и СУБД PostgreSQL. Вся 
информация по состоянию на 03 января 2025 года.

Скрипты могут работать и с СУБД MySQL, но в MySQL внутри БД нет схемы,
поэтому из скриптов надо удалить (закомментировать) строки:
```php
$schema = constant('SCHEMA');
$connection->exec("SET search_path TO {$schema}");
```
## Скачиваем архив с ФИАС с сайта налоговой (ФНС)

### Выбрать место для размещения файлов

Определяемся с тем где будем размещать архив ФИАС и другие рабочие 
файлы (1Tb file storage)

Если у нас виртуалка и нам не хватает места, то или расширяем раздел, 
или, что лучше для временных файлов, добавляем
новый диск, который после получения результата можно будет 
безболезненно удалить.

Инструкция для линукс систем:
[Как добавить или расширить диск в Linux](https://habr.com/ru/articles/871230/)

Допустим новый диск мы подключили к пути `mnt/pg-storage/`.

### Скачать архив данным ФИАС

Теперь надо получить ссылку на скачивание архива, находим
[страницу скачивания](https://fias.nalog.ru/Frontend), для этого:

1. Забиваем в интернет поисковик "ФНС ФИАС"
2. [Федеральная информационная адресная система](https://fias.nalog.ru/FiasInfo)
3. [Разработчикам](https://fias.nalog.ru/Frontend)
4. Копируем ссылку на архив из колонки "ПОЛНАЯ ВЕРСИЯ, XML"
5. Допустим ссылкой будет https://fias-file.nalog.ru/downloads/2024.10.29/gar_xml.zip
   Скачиваем архив туда где у нас достаточно свободного места
```shell
wget -P /mnt/pg-storage/download https://fias-file.nalog.ru/downloads/2024.10.29/gar_xml.zip
```
Развернём архив с помощью unzip, допустим в директорию
`/mnt/pg-storage/download/gar_xml`

### Удалить не нужные файлы

Удаляем не нужные файлы
```shell
cd /mnt/pg-storage/download/gar_xml
 find ./ -name "AS_ADDR_OBJ_DIVISION_*.XML" -exec rm {} \;
 find ./ -name "AS_APARTMENTS_*.XML" -exec rm {} \;
 find ./ -name "AS_CARPLACES_*.XML" -exec rm {} \;
 find ./ -name "AS_CHANGE_HISTORY_*.XML" -exec rm {} \;
 find ./ -name "AS_HOUSES_20*.XML" -exec rm {} \;
 find ./ -name "AS_NORMATIVE_DOCS_*.XML" -exec rm {} \;
 find ./ -name "AS_REESTR_OBJECTS_*.XML" -exec rm {} \;
 find ./ -name "AS_ROOMS_*.XML" -exec rm {} \;
 find ./ -name "AS_STEADS_*.XML" -exec rm {} \;
```
## Подготовим СУБД для импорта данных ФИАС

```shell
mkdir -p /mnt/pg-storage/data
```
Создадим табличное пространство для БД ФИАС
```postgresql
CREATE TABLESPACE fias_data
    OWNER postgres
    LOCATION '/mnt/pg-storage/data'
;
```
Создадим БД для ФИАС в соответствующем табличном пространстве
```postgresql
create database fias with owner postgres tablespace fias_data
;
```
Создадим схему для ФИАС в соответствующей БД
```postgresql
create schema gar
;
```
## Подготовка скриптов для импорта

### Настраиваем PHP

Узнаём путь к файлу с настройками PHP
```shell
php --ini
```
Откроем ini-файл с настройками PHP в текстовом редакторе (c Ubuntu в 
комплекте идёт `nano`)
Для быстрой работы скриптов надо включить OPCache и JIT (PHP 8.4)

Находим раздел `[opcache]`
```shell
opcache.jit=1255
opcache.jit_buffer_size=128M
opcache.enable=1
opcache.enable_cli=1
opcache.validate_timestamps=0
opcache.save_comments=0
;zend_extension = xdebug
xdebug.mode=off
```
Если установлено какое либо расширение для отладки PHP кода, то 
отключаем его

После того как скрипты отработают не забудьте восстановить исходные 
значения

### Настраиваем опции PHP скриптов
```shell
mkdir script && cd script
git clone https://github.com/SbWereWolf/extract-fias-params.git .
composer install
cp config.env.example config.env
nano config.env
```
Большинство значений можно оставить как есть, кроме реквизитов 
подключения к СУБД

Устанавливаем соответствующие значения для:
- LOGIN имя для подключения к СУБД
- PASSWORD пароль
- DSN строка подключения PHP скрипта к СУБД
- SCHEMA имя схемы, если используем PostgreSQL
- DO_IMPORT_WITH_CHECK при импорте всегда FALSE
- BATCH_SIZE количество записей для записи в БД за раз (100000)
- XML_FILES_PATH путь к файлам для импорта в БД

### Настраиваем собственно PHP скрипты

Распределяем файлы для импорта между скриптами, в настоящем варианте 
файлы разделены между тремя скриптами:
- data-import-01.php
- data-import-02.php
- data-import-03.php

В одном файле надо импортировать данные справочников (в работе 
скриптов справочники не участвуют, но для
самостоятельного исследования данных будут полезны)

За импорт справочников отвечает второй аргумент конструктора класса 
ImportOptions (`$referencePatterns`), это значения:
```php
    [
        AddHouseTypes::class =>
            'AS_ADDHOUSE_TYPES_20*.{x,X}{m,M}{l,L}',
        AddressObjectTypes::class =>
            'AS_ADDR_OBJ_TYPES_20*.{x,X}{m,M}{l,L}',
        ApartmentTypes::class =>
            'AS_APARTMENT_TYPES_20*.{x,X}{m,M}{l,L}',
        HouseTypes::class =>
            'AS_HOUSE_TYPES_20*.{x,X}{m,M}{l,L}',
        NormativeDocumentsKinds::class =>
            'AS_NORMATIVE_DOCS_KINDS_20*.{x,X}{m,M}{l,L}',
        NormativeDocumentsTypes::class =>
            'AS_NORMATIVE_DOCS_TYPES_20*.{x,X}{m,M}{l,L}',
        ObjectLevels::class =>
            'AS_OBJECT_LEVELS_20*.{x,X}{m,M}{l,L}',
        OperationTypes::class =>
            'AS_OPERATION_TYPES_20*.{x,X}{m,M}{l,L}',
        ParamTypes::class =>
            'AS_PARAM_TYPES_20*.{x,X}{m,M}{l,L}',
        RoomTypes::class =>
            'AS_ROOM_TYPES_20*.{x,X}{m,M}{l,L}',
    ],
```
Соответственно справочники надо импортировать только один раз, значит 
в одном из скриптов через этот аргумент надо
передать список классов парсеров, во всех других скриптах импорта 
второй аргумент
(`$referencePatterns`) должен быть пустым массивом - `[]`, иначе 
получим дублирование значений в справочниках

За то, из каких директорий скрипт будет брать файлы для импорта, 
отвечает третий аргумент (`$regionDataDirectoryPattern`
шаблон для поиска директорий)
```php
'{01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33}',
```
Всего директорий около ста, от 01 до 99, соответственно все эти 
директории надо распределить между скриптами.

Если сервер будет загружен только импортом данных ФИАС, то оптимально 
будет запустить скриптов на один меньше чем ядер у
сервера, то есть для 4-х ядерного - 3 скрипта, для 8-ми - 7 скриптов.

## Выполняем импорт данных

Как выполнить импорт полной базы ФИАС описано в статье
[Импорт полной базы ФИАС за 9 часов, How To](https://habr.com/ru/articles/714804/)

В настоящем варианте для извлечения индексов городов достаточно 
четырёх таблиц, скрипты уже соответствующим образом
подготовлены

Импорт состоит из следующих этапов

1. Создание таблиц в БД
2. Импорт данных
3. Создание индексов

### Создание таблиц в БД

Создание таблиц в БД это быстрый процесс, его необязательно выполнять
как фоновую задачу.
```shell
php ./install-storage.php
```
В БД будут созданы таблицы.

### Импорт данных

Перед импортом данных лучше отключить автовакуум.
```postgresql
ALTER SYSTEM SET AUTOVACUUM TO FALSE;
```
Импорт данных это долгий процесс, что бы при закрытии терминала импорт 
не был прекращен, лучше запустить импорт как фоновую задачу:
```shell
nohup php data-import-01.php &> import01.log &
```
Вывод скрипта будет перенаправлен в файл `import01.log`, логи можно 
будет отслеживать командой `tail`
```shell
tail import01.log
```
Так же каждый скрипт пишет свои логи в директорию `logs`.

Запускаем остальные скрипты
```shell
nohup php data-import-02.php &> import02.log &
nohup php data-import-03.php &> import03.log &
```
Когда скрипты закончат свою работу, тогда в логах появиться запись о 
том что импорт завершён. И в принципе по нагрузке на систему будут 
понятно (команда `top` в помощь).

Импорт может растянуться на несколько часов (6-12 часов). Запустите и 
займитесь другими делами.

### Создание индексов

Индексы создаются не долго, можно обойти без запуска в фоновом режиме:
```shell
php ./create-indexes.php
```
То какие индексы будут созданы для каждой таблицы определяется в 
соответствующем шаблоне по пути 
`template/%Имя таблицы%/create-index.php`, изменяйте на свой вкус

После импорта данных, когда индексы созданы, нужно включить сбор 
статистики для планировщика запросов, и обновить эту статистику.
```postgresql
ALTER SYSTEM SET autovacuum_vacuum_scale_factor = 0.00001;
ALTER SYSTEM SET autovacuum_vacuum_insert_scale_factor = 0.00001;
ALTER SYSTEM SET autovacuum_analyze_scale_factor = 0.00001;
ALTER SYSTEM SET AUTOVACUUM TO TRUE;

VACUUM FULL VERBOSE ANALYZE;
```
## Сформировать выгрузку

### Проблема с выгрузкой

Проблема с выгрузкой в том, что почтовые индексы есть у домов, но 
почтовых индексов нет у собственно населённых пунктов, но
у населённых пунктов конечно есть дома, и они связаны между собой
через таблицу ADM_HIERARCHY по административному делению.

Собственно задача состоит в том что бы сгруппировать почтовые индексы 
всех домов и взять минимальный индекс как индекс собственно 
населённого пункта (индекс населённого пункта обычно заканчивается
нолями, у подчинённых почтовых отделений индексы заканчиваются цифрами
от 1 до 9).

Почтовые индексы записаны не в таблицу домов, почтовые индексы это 
дополнительная информация и она записана в
таблицу `HOUSES_PARAMS`. Поэтому собственно файлы с данными 
домов `HOUSES` мы удалили как не нужные.

### Промежуточная таблица с индексами отдельных зданий

Создадим таблицу для почтовых индексов всех домов и заполним её 
данными
```postgresql
create table zip_code
(
    region   bigint,
    objectid bigint,
    value    text
)
    PARTITION BY LIST (region);
;
create unique index zip_code_region_objectid
    on zip_code (region, objectid);

insert into zip_code
select hp.region,
       hp.objectid,
       hp.value
from houses_params hp
where hp.typeid = 5
  and current_date::date between hp.startdate and hp.enddate
order by 
         hp.region,
         hp.objectid
;
```
В этом запросе можно текущую дату (`current_date::date`) поменять на 
произвольную.

База ФИАС ведётся как кадастр и включает в себя историю изменений, 
поэтому при запросах надо уточнять на какую дату вам нужны данные:
```postgresql
WHERE current_date::date between hp.startdate and hp.enddate
```
Интересующий нас параметр `Почтовый индекс`, имеет идентификатор 5:
```postgresql
hp.typeid = 5
```
Таблица ZIP_CODE является промежуточной, можно обойтись без неё, но
на этапе разработки постоянно формировать промежуточную выборку это
лишнее время на ожидание запроса, мои запросы её используют.

### Итоговая таблица с индексами населённых пунктов

Теперь надо создать таблицу для данных выгрузки по почтовым индексам.
```postgresql
create table gar.addr_obj_zip_code
(
    region   bigint,
    zip_code text,
    typename text,
    name     text,
    level    bigint,
    objectid bigint
);

create unique index addr_obj_zip_code_region_objectid_ux
    on gar.addr_obj_zip_code (region, objectid);
```
Данных в ней будет не так много, можно обойтись без партиционирования.

### Логика работы запроса

Что бы от дома с почтовым индексом перейти к населённому пункту и 
передать ему почтовый индекс надо построить иерархию, от дочерних
элементов - домов, к родительским - улица, районам и далее до субъекта
федерации.

Для каждого дома будет поднята вся "родня" до самого корня, далее для 
каждого уровня иерархии начиная с 6 будет выбрано минимальное 
значение индекса.

### Запрос с использованием Common Table Expression 

Строим иерархию с помощью CTE.

Нам надо соединить адресный объект (условную улицу) с почтовы индексом 
дома, у этих сущностей разный набор полей. 

Для первой строки иерархии, те поля которых нет у почтового индекса 
(название адресного объекта, тип адресного объекта, уровень в 
иерархии) подставляем как пустые.
```postgresql
null as typename, 
null as name, 
0::bigint as level
```
Следующую строку в иерархию добавляем по связке идентификатора
родительского объекта предыдущей строки с идентификатором объекта 
следующей.
```postgresql
ah.parentobjid = next.objectid
```
В следующую строку переносим из предыдущей строки только почтовый 
индекс `ah.value`.

Остальные данные в следующей строке подтягиваются для адресного 
объекта следующей строки:
- уровень в иерархии `a.level`
- наименование `a.name`
- название "уровня иерархии" `a.typename`
- уровень в иерархии `a.level`

Для вставки в результирующую таблицу берём только адресные объекты с
уровнем от 6 и выше (потому что LEVEL="6" это NAME="Населенный пункт", 
уровень ниже LEVEL="7" это NAME="Элемент планировочной структуры"
- микрорайон)

Скрипт работает не долго, на четырёх ядерном процессоре всего 15 минут
на всю Россию (я думаю что запрос выполнялся даже на одном ядре).
```postgresql
WITH RECURSIVE ah AS (
    SELECT 
           prev.region, 
           prev.parentobjid, 
           prev.objectid, 
           z.value, 
           null as typename, 
           null as name, 
           0::bigint as level
    FROM adm_hierarchy prev
             join zip_code z on
                    prev.region = z.region
                and prev.objectid = z.objectid 
                and prev.isactive = 1
    UNION ALL
    SELECT 
           next.region, 
           next.parentobjid, 
           next.objectid, 
           ah.value, 
           a.typename, 
           a.name, 
           a.level
    FROM adm_hierarchy next
             JOIN ah ON 
                     next.region = ah.region 
                 and next.objectid = ah.parentobjid 
                 and next.isactive = 1
             join addressobjects a on 
                     next.region = a.region 
                 and next.objectid = a.objectid 
                 and a.isactive = 1
)
insert
into addr_obj_zip_code
    (
     region, 
     zip_code, 
     TYPENAME, 
     NAME, 
     LEVEL, 
     objectid
     )
SELECT 
       ah.region, 
       min(ah.value) zip, 
       ah.typename, 
       ah.name, 
       ah.level, 
       ah.objectid
FROM ah
where ah.level between 1 and 6
group by ah.region, ah.typename, ah.name, ah.objectid, ah.level
;
```
## Дополнительная информация по адресным объектам

В ФИАС есть дополнительная информация для адресных объектов, 
такая как:
- ОКАТО (typeid=6)
- ОКТМО (typeid=7)
- КЛАДР (typeid=10)

Эту информацию добавить в выгрузку значительно легче.

Добавляем колонку
```postgresql
alter table gar.addr_obj_zip_code add okato text ;
```
Запрос на обновление выгрузки
```postgresql
update addr_obj_zip_code target
set okato = params.value
    from
     addressobjects source
        join addr_obj_params params on
                params.region = source.region 
            and params.objectid = source.objectid 
            and params.typeid=6
where
    target.region = source.region
  and target.objectid = source.objectid
  and source.isactive=1
  and current_date::date between params.startdate and params.enddate
;
```
С остальными дополнительными параметрами аналогично, добавляем 
колонку, подставляем соответствующий typeid (7 - ОКТМО, 10 - КЛАДР).

Делитесь в комментариях своим опытом работы с ФИАС.
