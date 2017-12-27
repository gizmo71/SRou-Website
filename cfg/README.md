This directory should contain the following files, which should be read only and visible only to the relevant user.

Under no circumstances should these files be committed to source control.

* `s3.secret`: secret key for making AWS S3 calls
* `smf-db.password`: password matching SROU_DB_USER
* `migrate-login.options`: user/password options for `mysql` when doing migration activities which cannot use normal login
