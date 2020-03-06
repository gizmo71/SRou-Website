This directory should contain the following files, which should be read only and visible only to the relevant user.

Under no circumstances should these files be committed to source control.

* `smf-db.password`: password matching SROU_DB_USER
* `migrate-login.options`: user/password options for `mysql` when doing migration activities which cannot use normal login
* `auth.secret`: used to secure cookies
* `imageproxy.secret`: prevents abuse of image proxy
