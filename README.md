# LinkLogin

Users can login with a link if the query contains the key login and a valid hash. The user_email_token field of the user table is used to save the hashes. In contrast to ordinary email tokens the user_email_token_expires field is set to null. The PopulateLoginLinks special page can be used to create hashes for all users in one of the `$wgLinkLoginGroups`.

As from address for the e-mails `$wgPasswordSender` will be used. The sender's name can be customized with the `Emailsender` system message.

LinkLogin users won't see the normal user preference form. Only the preferences defined in `$wgLinkLoginPreferences` will be shown to them. They can also be edited by users with `edituser` right (defined by EditUser extension). These preferences can be used as variables in templates that define the subject and body of mailings.


If you define a template to create the subject the content of the subject field will be ignored.

## Installation

### Dependencies

In order for this extension to work the following extensions need to be installed:
* EditUser
* SemanticOrganization

The extension uses Bootstrap classes and therefore works best with skins based on Bootstrap. It has only been tested with Tweeki skin.


## Usage


## Configuration Options

### $wgLinkLoginGroups

Use this configuration option to define groups whose members can be LinkLogin members. Only members of these groups can be recipients for mailings.

### $wgLinkLoginPreferences

Use this option to define additional preferences. The default is:

```
$wgLinkLoginPreferences = [
	'email' => [
		'type' => 'email',
	]
];
```

HTMLForm's syntax can be used to define additional fields.


## Parser Functions

### {{#linklogin-recipients:mailing=|before=|after=}} (tbd)

Gets comma separated list of a mailing's recipients.

### {{#linklogin-logins:before=|after=}} (tbd)

Gets comma separated list of logins.


## Special Pages 

### PopulateLoginLinks

whose e-mail address hasn't been set

### Mailings

Lists all mailings, allows to create, edit, and send mailings.

To change the columns shown in the detailed view for a mailing, use `MediaWiki:linklogin-columns`. By default all the preferences defined by `$wgLinkLoginPreferences` will be shown in their own column. If you define a column with no corresponding preference it is assumed, that a template should be used. In the template you can use all the preferences as variables.

### EditMailing

Defines the form to create and edit mailings.

### LoginLog

Shows successful logins.

### LoginAttemptLog

Shows login attempts with invalid hashes.


## Rights

### populateloginlinks

populate the user table with login links

### mailings

create and send mailings

### loginlogs

inspect login logs


## Todo

* there should be a possibility to reset the hash
