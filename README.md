# LinkLogin

Users can login with a link if the query contains the key login and a valid hash. The user_email_token field of the user table is used to save the hashes. In contrast to ordinary email tokens the user_email_token_expires field is set to null. The PopulateLoginLinks special page can be used to create hashes for all users in one of the `$wgLinkLoginGroups`.

As from address for the e-mails `$wgPasswordSender` will be used. The sender's name can be customized with the `Emailsender` system message.

## Special Pages 

### Special Page PopulateLoginLinks

whose e-mail address hasn't been set


## Todo

* there should be a possibility to reset the hash
