# Auto Caller

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

<p align="left"> <a href="https://www.php.net" target="_blank" rel="noreferrer"> <img src="https://raw.githubusercontent.com/devicons/devicon/master/icons/php/php-original.svg" alt="php" width="40" height="40"/> </a> </p>

Auto Call Voice Sender For Asterisk, Issabel and Elastix.
If you want to send a voice message to a list of numbers and play it automatically, install this script via SSH on your Issabel or Elastix server.

Attention: You should have a SIP trunk number to use this script. If you have an analog-to-digital gateway, it will not work correctly — use a SIP trunk for outgoing calls. Local extensions work without any problem.

# Install Service: SSH remote on Issabel or Elastix

```
wget https://raw.githubusercontent.com/miladonline1/VOIP-Auto-Caller/main/install.sh && chmod 755 install.sh && sh install.sh
```

Attention: First be sure what your MySQL root password is! If you enter the wrong password, the script can't work.

# How to Use?

Open this URL **without https**: `[issabel_or_elastix ip]/autocaller`

![This is an image](https://raw.githubusercontent.com/miladonline1/VOIP-Auto-Caller/main/ISSABEL-Auto-Dialler.jpg)

# HELP

1- Wait Time: Wait for user to accept the call (seconds)

2- Interval: Space between each call (seconds), depending on how many channels your SIP trunk supports (multi-call)

3- Caller ID: it's the local caller id, doesn't matter what you set!

4- Prefix: used for outbound calls

5- Press Number 1 or 2: if the user presses 1 or 2 during the voice message, the call will be redirected to the extension you set

6- Upload Number: download the example file and import your numbers with the voice name

7- History: shows who accepted the call or not, exportable to Excel

8- Manage Voice: upload voice with WAV or MP3, 8000Hz, 16-bit, Mono

9- API: you can use the URL API, for example:
`http://[issabelip]/autocaller/api.php?action=democall&phone=[phone_number]&file=[audio_name.wav]&action=call`

# Automatic Dialer Features

- PHP script structure
- Open source
- Pitching at a given time
- Scheduled calls with API
- API capability and connection
- Simple and easy to use
- Compatible with Asterisk (Elastix, Issabel)
- IVR scenario definition
- Monitoring of ongoing calls
- Ability to report successful and unsuccessful calls
- The possibility of multiple simultaneous calls with different messages
- You can distinguish between active and inactive numbers

---
[imapro.ir](https://imapro.ir)
