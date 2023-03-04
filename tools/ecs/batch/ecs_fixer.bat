:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
php vendor\bin\ecs check vendor/markocupic/gallery-creator-bundle/src --fix --config vendor/markocupic/gallery-creator-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/gallery-creator-bundle/contao --fix --config vendor/markocupic/gallery-creator-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/gallery-creator-bundle/config --fix --config vendor/markocupic/gallery-creator-bundle/tools/ecs/config.php
:: php vendor\bin\ecs check vendor/markocupic/gallery-creator-bundle/templates --fix --config vendor/markocupic/gallery-creator-bundle/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/gallery-creator-bundle/tests --fix --config vendor/markocupic/gallery-creator-bundle/tools/ecs/config.php


