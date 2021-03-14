# Installation of Part-DB via docker

**Warning: The methods described here, configure PHP without HTTPS and therefore should only be used locally. If you want to expose Part-DB to the internet, you have to configure a reverse proxy!**

## Docker-compose
Part-DB can be installed via docker. A pre-built docker image is available under [jbtronics/part-db1](https://hub.docker.com/repository/docker/jbtronics/part-db1/).
In the moment the master tag should be used (which is built from the latest commits in the master branch), as no tagged releases are available yet.

The easiest way to use it is to use the docker-compose.yml available [here](https://raw.githubusercontent.com/Part-DB/Part-DB-symfony/master/docs/docker/docker-compose.yaml):
0. Install docker and docker-compose like described under https://docs.docker.com/compose/install/
1. Create a folder where the Part-DB data should live
2. Download docker-compose.yml and move it to the folder created above
3. Inside the folder, run `docker-compose up -d`
4. Create the inital database with `docker exec --user=www-data partdb bin/console doctrine:migrations:migrate` and watch for the password output
5. Part-DB is available under `http://localhost:8080` and you can log in with username `admin` and the password shown before

The docker image uses a SQLite database and all data (database, uploads and other media) is put into folders relative to the docker-compose.yml.

## Direct use of docker image
You can use the `jbtronics/part-db1:master` image directly. You have to expose the port 80 to a host port and configure volumes for `/var/www/html/uploads` and `/var/www/html/public/media`.

You also have to create the database like described above in step 4.

## Running console commands
You can run the console commands described in README by executing `docker exec --user=www-data -it partdb bin/console [command]`

## Troubleshooting

*Login not possible. Login page is just reloading and no error message is shown or something like "CSFR token invalid"*:

Clear all cookies in your browser or use a inkognito tab for Part-DB.
This related to the fact that Part-DB can not set cookies via HTTP, after some webpage has set cookies before under localhost via https. This is a security mechanism of the browser and can not be bypassed by Part-DB.
