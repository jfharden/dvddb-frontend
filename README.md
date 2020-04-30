# DVD Database Frontend

This is a php website with no built in auth, that is a search frontend for a database of DVDs I created many years ago.

The general layout of the repo was cloned from 
[my template php docker service repo](https://github.com/jfharden/template-php-docker-site) and so is now secured with
OpenID Connect in real life.

The php code is _not_ good, but that's ok because I made it a long, long time ago, and if we can't look back at our old
code and see a lot of room for improvement then maybe we haven't advanced.

## Requirements

### To build the project

1. go 1.13+ (for the tests)
2. docker-compose (any version which supports compose templates 3+, written and tested with 1.25)

### To run the docker container without the docker-compose file

1. Env vars:
    1. DB\_HOST: Host name of the postgres instance to connect to
    2. DB\_NAME: Name of the database inside the postgres host
    3. DB\_USER: Username to auth for postgres
    4. DB\_PASS: Password to auth for postgres
    5. SSLMODE: PHP postgres SSL mode (verify-full suggested for production)
    6. SSLROOTCERT: The path to the SSL cert for verifying the SSL connection to the server (for AWS RDS (which is
       included in the docker container for you) set this to `/secrets/rds-combined-ca-bundle.pem`)
    7. To optionally enable auth either:
        1. HTPASSWD\_FILE: The content to put into the htpasswd file
        2. OPENID\_ENABLED=true - See [OpenID Connect authentication](#openid-auth)

## <a id="openid-auth">OpenID Connect authentication</a>

To enable OpenID auth you need to set the following env vars:

Env var | Value | Notes
--- | --- | ---
OPENID\_ENABLED | "true" | Must be the string true
OPENID\_METADATA\_URL | The well known metadata url for your provider | In cognito this is `https://cognito-idp.<REGION>.amazonaws.com/<COGNITO_USER_POOL_ID>/.well-known/openid-configuration`
OPENID\_CLIENT\_ID | The clientid for your client as specified by your open id provider |
OPENID\_SECRET | The client secret for your clientas specified by your open id provider |
OPENID\_REDIRECT\_URL | The redirect URI which your provider will return the user to in your application | This needs to be set to `https://<YOUR_DOMAIN>/redirect_uri` to match the apache module configuration
OPENID\_CRYPTO\_PASSPHRASE | The passpharse mod\_auth\_openidc will use to encrypt secrets | See the [mod\_auth\_openidc config file for more info](https://github.com/zmartzone/mod_auth_openidc/blob/master/auth_openidc.conf#L16)
OPENID\_END\_SESSION\_ENDPOINT | The logout url for your open id provider | Some providers (looking at you AWS Cognito) do not provide this from the metadata endpoint, for any provider that doesn't you will need to set this explicitly.

***Special notes about OPENID\_END\_SESSION\_ENDPOINT***

**Note:** In the following the logout\_uri parameter in the OPENID\_END\_SESSION\_ENDPOINT, the logout parameter in the
logout link on your site, and the "Sign out URL(s)" in the AWS Cognito "App Client Settings" are all _identical_.

For AWS Cognito the OPENID\_END\_SESSION\_ENDPOINT env var should be:

    https://<AMAZON_COGNITO_DOMAIN>/logout?client_id=<APP_CLIENT_ID>&logout_uri=<SIGN_OUT_URL_AS_SET_IN_COGNITO_APP_CLIENT_SETTINGS>

The logout\_uri parameter needs to be a page in your site, which is _not_ protected by openid connect (this is defaulted to `src/loggedout.php` in our config).

In your app a logout link needs to be of this format:

    https://<YOUR_DOMAIN>/redirect_uri?logout=https%3A%2F%2F127.0.0.1%2Floggedout.php

**Note:** The logout parameter has to be IDENITICAL (but URI encoded!) to the "Sign out URL(s)" you specified in the AWS Cognito "App Client Settings"


