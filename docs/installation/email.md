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
automatic mail providers (like MailChimp or SendGrid). If you want to use one of these providers, check the Symfony
Mailer documentation for more information.

We will only cover the configuration of an SMTP provider here, which is sufficient for most use-cases.
You will need an email account, which you can use send emails from via password-bases SMTP authentication, this account
should be dedicated to Part-DB.

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