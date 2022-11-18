# magento-plugin
Plugin de magento para checkout

# D-una Plugin setup

 - Go to the project **app/code** folder and create the new module folder DIR.
 - Module folder DIR - **DUna/Payments**
 - clone GIP repository inside the module **Payments** DIR.
 - After run below magento setup command.

  ```
    sudo chmod -R 777 var var/* generated/ generated/* pub pub/static/*
  ```

  ```
   php bin/magento setup:upgrade && php bin/magento s:d:c && php bin/magento setup:static-content:deploy -f && php bin/magento cache:flush
  ```
  

### Tutorial Desarrollo Local 

### Configuración 

Conectarse a la base de datos y ejecutar un alter a la tabla **quote**
en 600.000

```sql
ALTER TABLE magento.quote AUTO_INCREMENT = 600000

```

### Luego de esto nos vamos a **nrok** para poder levantar el checkout

```ssh
ngrok http --region=us --hostname=palejos-magento-test.ngrok.io local.magento.com
```

### Ahora tenemos que actualizar en la base de datos los siguientes valores en la tabla **magento.core_config_data;**

## se actualiza url para secure
```sql
UPDATE magento.core_config_data
SET `scope`='default', scope_id=0, `path`='web/secure/base_url', value='https://palejos-magento-dev.ngrok.io/', updated_at='2022-10-28 14:47:02'
WHERE config_id=36;

```

## se actualiza url para unsecure
```sql
UPDATE magento.core_config_data
SET `scope`='default', scope_id=0, `path`='web/unsecure/base_url', value='http://palejos-magento-dev.ngrok.io/', updated_at='2022-10-28 14:42:54'
WHERE config_id=7;

```
Luego ejecutamos el siguiente script para refrescar los cambios 

ruta donde se ejecuta el script 

```sh
/usr/local/var/www/magento
```
```ssh
sh clear.sh
```


clear.sh
```bash
#! /bin/bash
echo "Executing Setup Upgrade..."
php bin/magento setup:upgrade
echo "Executing Setup Compile..."
php bin/magento setup:di:compile
echo "Deploying Static Content..."
php bin/magento setup:static-content:deploy -f
echo "Cleaning Cache..."
php bin/magento cache:clean

```

Para más detalle ver documentación en notion https://www.notion.so/deuna/Instalaci-n-Magento-2-4-Localhost-e65a579d52a74b0590d6aa8742bd51fe