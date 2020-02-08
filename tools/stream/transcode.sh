#!/bin/bash

DIR="$(cd "$(dirname "$0")" && pwd)"

while [ 1 ]
do
  sleep 900
  php $DIR/transcode.php >> $DIR/transcode.log 2>&1
done
