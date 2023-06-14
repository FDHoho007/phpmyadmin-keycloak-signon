# phpMyAdmin KeyCloak Signon

This php script is intended to be used with the [phpMyAdmin Docker image](https://hub.docker.com/r/phpmyadmin/phpmyadmin/). It will be applied as a signon script to every server in this phpMyAdmin instance by default. The script will authorize the user at the keycloak server und then check his permissions for this client (phpMyAdmin instance). Therefore each database server has to be manually created as a resource (for this client) and permission policies set approprioately. If a user has access to a resource called excatly like the database hostname then access to phpMyAdmin will be granted. This also requires phpMyAdmin to know the DB credentials and therefore be configured as if auth_type config is used.

#### Compose sample file
```
version: "3.9"
services:
  phpmyadmin:
    image: docker.io/phpmyadmin/phpmyadmin
    volumes:
      - ./phpmyadmin/oidc-signon.php:/var/www/html/oidc-signon.php
    environment:
      PMA_HOST: database
      PMA_USER: root
      PMA_PASSWORD: root
      PMA_USER_CONFIG_BASE64: "PD9waHAKZm9yKCRpID0gMTsgJGk8PXNpemVvZigkY2ZnWydTZXJ2ZXJzJ10pOyAkaSsrKSB7CiAgICAkY2ZnWydTZXJ2ZXJzJ11bJGldWydhdXRoX3R5cGUnXSA9ICdzaWdub24nOwogICAgJGNmZ1snU2VydmVycyddWyRpXVsnU2lnbm9uU2Vzc2lvbiddID0gJ1NpZ25vblNlc3Npb24nOwogICAgJGNmZ1snU2VydmVycyddWyRpXVsnU2lnbm9uVVJMJ10gPSAnL29pZGMtc2lnbm9uLnBocD9kYXRhYmFzZT0nIC4gJGNmZ1snU2VydmVycyddWyRpXVsnaG9zdCddOwp9"
      OIDC_URL: <Your Keycloak Server>/realms/master
      OIDC_CLIENT_ID: phpmyadmin
      OIDC_CLIENT_SECRET: <Keycloak Client Secret>
```

PMA_USER_CONFIG_BASE64 must be kept excatly like this in order to setp signon as auth type. You are expected to create and setup the client required for phpMyAdmin in your Keycloak instance by yourself. Make sure `Client authentication` and `Authorization` are enabled and that every database hostname is present as a resource.