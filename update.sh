##!/bin/bash
set -euo pipefail

### constants ###
FILE="Part-DB-release-stage"
GITREPO="/opt/partdb/git-repo"
PRODENV="/var/www/Part-DB-server"
BACKUPDIR="/HDD5/backups/partdb"
WEB_AVAILABLE="/etc/apache2/sites-available"
WEB_ENABLED="/etc/apache2/sites-enabled"
WEBSERVICE="apache2"
LIVE_CONF="01_partdb.conf"
TEST_CONF="06_partdb_testing.conf"
MNT_CONF="07_partdb_maintenance.conf"

# We should probably do a sanity check here?
DBUSER="$1"
DBPASSWD="$2"

### variables ###
stage="1"

echo ""
echo "*** Part-DB upgrade tool v0.0001 (WorksForMe* edition) ***"


if [ $(whoami) != 'root' ]; then
        echo ""
        echo "This script must be run as root!"
        exit -1
fi

if [ -f $FILE ]; then
        stage=$(<$FILE)
fi

if [ "$stage" = "1" ]; then # no update currently in progress
        cd $GITREPO
        git fetch --tags
        # get latest version
        gitver=$(git describe --tags --abbrev=0)
        currentver=$(</var/www/Part-DB-server/VERSION)
        if [ "$gitver" = "v$currentver" ]; then
                echo "* Already up-to-date!"
                exit 0
        fi
        echo "New version $gitver avaliable (currently on $currentver)."
fi

for curstage in $(seq $(($stage)) 4)
do
    echo ""
    echo "* Stage $curstage: continue? (y/n)"
    read -r response
    echo ""
    if [ "$response" = "y" ]; then
        case $curstage in
        "1")
        if [ "$stage" = "1" ]; then
                echo "* Stage 1: Put Part-DB in maintenance mode"
                # remove link to current PartDB VHost
                rm -f $WEB_ENABLED/$LIVE_CONF
                # put up a maintenance notice on the URL
                ln -sf $WEB_AVAILABLE/$MNT_CONF $WEB_ENABLED/$LIVE_CONF
                # put up extra url for testing new part-db (not necessary, may always exist)
#               ln -sf $WEB_AVAILABLE/partdb_testing.conf $WEB_ENABLED/partdb_testing.conf
                # reload apache
                if ! [ `systemctl reload $WEBSERVICE && systemctl is-active --quiet $WEBSERVICE` ]; then
                        echo "* Webserver restart failed! Please check your $WEBSERVICE site configurations."
                        break
                fi
                echo "* Part-DB now in maintenance mode, update may proceed."
                echo "2" > $FILE
        else
                echo "* Invalid stage: $stage, expected 1"
                exit 1
        fi
        ;;
        "2")
        if [ "$stage" = "2" ]; then
                echo "* Stage 2: Dump DB and update Part-DB via git"
                # cd into working dir
                cd $GITREPO
                git fetch --tags
                # get latest version
                version=$(git describe --tags --abbrev=0)
                # dump DB, preventing overwrite by re-execution if e.g. the migration broke the database structure
                mysqldump -u$DBUSER -p$DBPASSWD partdb > $BACKUPDIR/partdb_before_update_$version_$(date -Iseconds).sql
                # pull changes, checkout latest tag
                git pull && git checkout $version
                # copy config and media files and correct ownership
                cp "$PRODENV/.env.local" $GITREPO
                cp -rn $PRODENV/public/media/ $GITREPO/public/
                chown -R www-data:www-data $GITREPO
                # merge .env with .env.local, config/services.yaml, config/parameters.yaml if changed
                # TODO how to handle customizations ??? meld ???
                echo "* Files are in place, build step pending"
                echo "3" > $FILE
        else
                echo "* Invalid stage: $stage, expected 2"
                exit 2
        fi
        ;;
        "3")
        if [ "$stage" = "3" ] ; then
                echo "* Stage 3: Build process"
                # build steps
                cd $GITREPO
                environment=$(sed -nr 's/APP_ENV=(.*)/\1/p' .env.local)
                if [ environment != "dev" ]; then
                        environment="no-dev"
                fi
                sudo -u www-data composer install --$environment -o
                yarn install
                yarn build
                # check if installation succeeded and migrate db
                sudo -u www-data php bin/console partdb:check-requirements
                sudo -u www-data php bin/console doctrine:migrations:migrate
                sudo -u www-data php bin/console cache:clear
                # we can mess with the production db because we have a very recent backup
                rsync -av --exclude=$GITREPO/.git* $GITREPO/ $PRODENV-test

                echo "* The new Part-DB version can now be tested. You may need to merge .env.local with .env and check yaml files in config/."
                echo "4" > $FILE
        else
                echo "* Invalid stage: $stage, expected 3"
                exit 3
        fi
        ;;
        "4")
        if [ "$stage" = "4" ]; then
                echo "Stage 4: Put Part-DB back in production mode, retaining the old copy"
                # copy all to prod environment
                mv $PRODENV $PRODENV-old
                mv $PRODENV-test $PRODENV
                # remove link to maintenance PartDB VHost
                rm $WEB_ENABLED/$LIVE_CONF $WEB_ENABLED/$TEST_CONF
                # link the new partdb version
                ln -sf $WEB_AVAILABLE/$LIVE_CONF $WEB_ENABLED/$LIVE_CONF
                # reload apache
                if ! [ `systemctl reload $WEBSERVICE && systemctl is-active --quiet $WEBSERVICE` ]; then
                        echo "* Webserver restart failed! Please check your $WEBSERVICE site configurations."
                        break
                fi
                echo ""
                echo "*** Done. ***"
                rm $FILE
        else
                echo "* Invalid stage: $stage, expected 4"
                exit 4
        fi
        ;;
        esac
        if [ -f $FILE ]; then
            stage=$(<$FILE)
        else
            stage="1"
        fi
    else
        echo "Update process aborted before stage $curstage."
        break
    fi
done
exit 0
