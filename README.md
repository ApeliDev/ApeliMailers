# ApeliMailers

📧 Lightweight PHP email library with SMTP, queues, and analytics

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

## Overview

ApeliMailers is a PHP email library providing SMTP support, email queuing capabilities, and analytics. It's designed for developers who need a lightweight, flexible solution for handling email operations.

## Features

- **SMTP Support**: Send emails via SMTP with TLS/SSL encryption
- **Multiple Transport Options**: Support for SMTP, Sendmail, and custom transports
- **Email Analytics**: Track email performance metrics
- **Security-Focused**: Built with security best practices
- **Configurable**: Adaptable to various email service providers
- **Extensible Architecture**: Create custom transport adapters

## Installation

```bash
composer require apeli/apelimailers
```

## Basic Usage

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use ApeliMailers\Core\Mailer;
use ApeliMailers\Transport\SmtpTransport;

// Configure transport
$transport = new SmtpTransport(
    'smtp.example.com',
    587,
    'username',
    'password',
    'tls'
);

// Initialize mailer
$mailer = new Mailer($transport);

// Create and send message
$message = $mailer->createMessage()
    ->from('sender@example.com', 'Sender Name')
    ->to('recipient@example.com', 'Recipient Name')
    ->subject('Hello from ApeliMailers')
    ->html('<h1>Welcome!</h1><p>This is an email sent with ApeliMailers.</p>');

$result = $mailer->send($message);
```

## Configuration

### Transport Options

#### SMTP Transport

```php
$transport = new SmtpTransport(
    'smtp.example.com',  // Host
    587,                 // Port
    'username',          // Username
    'password',          // Password
    'tls',               // Encryption: 'tls', 'ssl', or null
    false                // Debug mode (optional)
);
```

#### Sendmail Transport

```php
use ApeliMailers\Transport\SendmailTransport;

$transport = new SendmailTransport('/usr/sbin/sendmail -bs');
```

### Environment Configuration

Create a `.env` file in your project root:

```
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=username
MAIL_PASSWORD=password
MAIL_ENCRYPTION=tls
```

Load configuration in your code:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$transport = new SmtpTransport(
    $_ENV['MAIL_HOST'],
    $_ENV['MAIL_PORT'],
    $_ENV['MAIL_USERNAME'],
    $_ENV['MAIL_PASSWORD'],
    $_ENV['MAIL_ENCRYPTION']
);
```

### Adding Attachments

```php
$message->addAttachment('/path/to/file.pdf', 'document.pdf');
```

### Managing Recipients

```php
$message->to('recipient@example.com', 'Recipient Name')
        ->cc('cc@example.com', 'CC Recipient')
        ->bcc('bcc@example.com', 'BCC Recipient')
        ->replyTo('reply@example.com', 'Reply Handler');
```


## License

ApeliMailers is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).