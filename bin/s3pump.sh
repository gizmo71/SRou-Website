#!/bin/sh -x

cd ~/public_ftp/s3pump || exit 1
find * -type d | while read dir; do
	test -d ../s3pump.sent/$dir || mkdir ../s3pump.sent/$dir || exit 1
done
find * -type f | while read file; do
	s3cmd --verbose --reduced-redundancy put $file s3://awsdownloads.simracing.org.uk/$file || exit 1
	mv $file ../s3pump.sent/
done
