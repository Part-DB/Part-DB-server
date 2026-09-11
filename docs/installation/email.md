---
title: Email
layout: default
parent: Installation
nav_order: 12
---

# Email

Part-DB can communicate with its users via email.
At the moment this is only used to send password reset links, but in future this will be used for other things too.

To make emails work you have to properly configure a mail provider in Part-DB.

## Configuration

Part-DB uses [Symfony Mailer](https://symfony.com/doc/current/mailer.html) to send emails, which supports multiple
mail providers (like Mailgun, SendGrid, or Brevo). If you want to use one of these providers, check the Symfony
Mailer documentation for more information.

We will only cover the configuration of an SMTP provider here, which is sufficient for most use-cases.
You will need an email account, which you can use to send emails from via password-based SMTP authentication, this account
should be dedicated to Part-DB.

### Using specialized mail providers (Mailgun, SendGrid, etc.)

If you want to use a specialized mail provider like Mailgun, SendGrid, Brevo (formerly Sendinblue), Amazon SES, or 
Postmark instead of SMTP, you need to install the corresponding Symfony mailer package first.

#### Docker installation

If you are using Part-DB in Docker, you can install additional mailer packages by setting the `COMPOSER_EXTRA_PACKAGES` 
environment variable in your `docker-compose.yaml`:

```yaml
environment:
  - COMPOSER_EXTRA_PACKAGES=symfony/mailgun-mailer
  - MAILER_DSN=mailgun+api://API_KEY:DOMAIN@default
  - EMAIL_SENDER_EMAIL=noreply@yourdomain.com
  - EMAIL_SENDER_NAME=Part-DB
  - ALLOW_EMAIL_PW_RESET=1
```

You can install multiple packages by separating them with spaces:

```yaml
environment:
  - COMPOSER_EXTRA_PACKAGES=symfony/mailgun-mailer symfony/sendgrid-mailer
```

The packages will be installed automatically when the container starts.

Common mailer packages:
- `symfony/mailgun-mailer` - For [Mailgun](https://www.mailgun.com/)
- `symfony/sendgrid-mailer` - For [SendGrid](https://sendgrid.com/)
- `symfony/brevo-mailer` - For [Brevo](https://www.brevo.com/) (formerly Sendinblue)
- `symfony/amazon-mailer` - For [Amazon SES](https://aws.amazon.com/ses/)
- `symfony/postmark-mailer` - For [Postmark](https://postmarkapp.com/)

#### Direct installation (non-Docker)

If you have installed Part-DB directly on your server (not in Docker), you need to manually install the required 
mailer package using composer.

Navigate to your Part-DB installation directory and run:

```bash
# Install the package as the web server user
sudo -u www-data composer require symfony/mailgun-mailer

# Clear the cache
sudo -u www-data php bin/console cache:clear
```

Replace `symfony/mailgun-mailer` with the package you need. You can install multiple packages at once:

```bash
sudo -u www-data composer require symfony/mailgun-mailer symfony/sendgrid-mailer
```

After installing the package, configure the `MAILER_DSN` in your `.env.local` file according to the provider's 
documentation (see [Symfony Mailer documentation](https://symfony.com/doc/current/mailer.html) for DSN format for 
each provider).

## SMTP Configuration

To configure the SMTP provider, you have to set the following environment variables:

`MAILER_DSN`: You have to provide the SMTP server address and the credentials for the email account here. The format is
the following:
`smtp://<username>:<password>@<smtp-server-address>:<port>`. In most cases the username is the email address of the
account, and the port is 587.
So the resulting DSN could look like this: `smtp://j.doe@mail.invalid:SUPER_SECRET_PA$$WORD@smtp.mail.invalid:587`.

`EMAIL_SENDER_EMAIL`: This is the email address which will be used as sender address for all emails sent by Part-DB.
This should be the same email address as the one used in the `MAILER_DSN` (the email address of your email account):
e.g. `j.doe@mail.invalid`.

`EMAIL_SENDER_NAME`: This is the name which will be used as sender name for all emails sent by Part-DB.
This can be anything you want, e.g. `My Part-DB Mailer`.

Now you can enable the possibility to reset password by setting the `ALLOW_EMAIL_PW_RESET` env to `1` (or `true`).