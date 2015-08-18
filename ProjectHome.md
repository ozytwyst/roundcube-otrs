This plugin allows webmail users to submit tickets to OTRS

The plugin has four prerequisites to work:
  1. A configured OTRS installation
  1. Enable the RPC interface in OTRS (you have to set a user name and password under `Admin > SysConfig > Framework > Core::Soap`)
  1. Perl module SOAP::Lite installed on yours OTRS server side
  1. php-soap installed on yours `RoundCube `server side