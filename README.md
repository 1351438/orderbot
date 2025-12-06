# Admin
after running the bot send a message to the bot using your account and in the database users table change your own type to ADMIN
send /manage to the bot to control the bot and see stats

you can also edit `/controller/admin/index.php` for more actions

# City & Region
you have at first add the cities you activity on `city` table and then regions of activity on `region` table 

# Manager
same as admin change their type to `SELLER` and in each product you have to define the admin user id.

# Driver
same ad admin and manger but the type will be `DRIVER` and also in the `drivers` table you must insert a new record of driver information

# Products
in the products table you must insert the products base on the regions and cities that you have modify them in the `city` table and `region` table and use the regions id for the product each product can have only one manager the price is in TON coins and the `driver_note` column will be only displays to the drivers should be the information of the source.

# Receiver wallet
in the `wallets` table you have add only one record for the receiving money wallet

# Install
copy the files with .copy extension and remove that .copy part and then edit the following codes.
update `.env` and `/config/config.php` and `/config/database.php` for the configuration

upload db.sql into the phpmyadmin for create tables.

### set webhook
https://youdomain.com/?set=true


### cronjobs
for fetch the ton network events, and settle orders and delete messages

    1 minute => https://youdomain.com/?cron=events
    2 minute => https://youdomain.com/?cron=settle_orders
    3 minute => https://youdomain.com/?cron=delete_messages
