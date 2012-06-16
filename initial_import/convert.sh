#!/bin/bash

mysql -u root -p tomyedwa_tlbudget2011 -e "source tomyedwa_tlbudget2011.sql"
mysqldump --xml -u root -p tomyedwa_tlbudget2011 > budget2011.xml
