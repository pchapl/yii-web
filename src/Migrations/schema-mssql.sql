/**
 * Database schema required by \Yiisoft\Web\DbSession.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 * @since 2.0.8
 */

if object_id('[session]', 'U') is not null
    drop table [session];

create table [session]
(
    [id]  varchar(256) not null,
    [expire] integer,
    [data]   nvarchar(max),
    primary key ([id])
);
