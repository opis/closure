# Security Policy

## Supported Versions

Use this section to tell people about which versions of your project are
currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 5.1.x   | :white_check_mark: |
| 5.0.x   | :x:                |
| 4.0.x   | :white_check_mark: |
| < 4.0   | :x:                |

## Reporting a Vulnerability

Use this section to tell people how to report a vulnerability.

Tell them where to go, how often they can expect to get an update on a
reported vulnerability, what to expect if the vulnerability is accepted or
declined, etc.
Hi,

we face security issue.. need for solution..

issue detail given below

package name : opis/closure

Package link : https://github.com/opis/closure.git

issue description : 

line numbers :
a) vendor/opis/closure/src/ReflectionClosure.php:750

1) An attacker can exploit CRC32’s weaknesses to manipulate data, leading to potential security breaches and loss of data integrity.

solution need like this : It is recommended to avoid using CRC32 for hashing when security is a concern. Instead, opt for stronger hashing algorithms like SHA-256 to ensure data integrity and security.


thanks
