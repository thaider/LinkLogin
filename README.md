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

Minimal Setup:

Don't forget to run the database updates:

```
php maintenance/update.php
```

Load the extension and define `$wgLinkLoginGroups` in `LocalSettings.php`, e.g.

```
wfLoadExtension('LinkLogin');
$wgLinkLoginGroups = [
  'contact' => [
    'categories' => [
      'MyCategory'
    ]
  ]
];
```

Make sure the EditUser extension is also installed.

Now if you have a page in the `MyCategory` category, you can go to `Special:LinkLogin Pages` and link the page to a user. Edit the user to add an email address. After that, on `Special:Mailings` you can create a mailing.

Make sure that the SMTP server to send Mails is configured correctly.


## Configuration Options

### `$wgLinkLoginGroups`

Use this configuration option to define groups whose members can be LinkLogin members. Only members of these groups can be recipients for mailings.

```
$wgLinkLoginGroups = [
  'contact' => [
    'categories' => [
      'MyCategory'
    ]
  ]
];
```


### `$wgLinkLoginCategories`

Use this to define optional configuration options for specific categories. Currently the only available option is `filter`.

Example:

```
$wgLinkLoginCategories = [
	'MyCategory' => [
		'filter' => '[[MyProperty::true]]',
	]
];
```

### `$wgLinkLoginPreferences`

Use this option to define additional preferences. The default is:

```
$wgLinkLoginPreferences = [
	'email' => [
		'type' => 'email',
	]
];
```

HTMLForm's syntax can be used to define additional fields.

To add an extra preference set $wgLinkLoginPreferences like this:
```
$wgLinkLoginPreferences['work'] = [
    'type' => 'email',
];
```

### `$wgLinkLoginDelimiter`

Delimiter to be used for user lists for inclusion/exclusion and also for the parser functions.

### `$wgLinkLoginAttemptlogNotify`

Set it to an e-mail address if you want to be notified after every `$wgLinkLoginAttemtplogThreshold` failed login attempts.

### `$wgLinkLoginAttemptlogThreshold`

The default is `100`.

### `$wgLinkLoginAttemptlogPause`

A notification will only be sent, if the last one was sent at least this many seconds ago. The default is `86400` (24 hours).

### `$wgLinkLoginEditableNamespaces`

LinkLogin users by default can only edit pages linked to their account. Use this option to define namespaces within which LinkLogin users are free to create and edit pages.

Example:

```
$wgLinkLoginEditableNamespaces = [
	'3000' => true,
];
```

## Parser Functions

### `{{#linklogin-recipients:mailing=|before=|after=}}`

Gets delimiter separated list of a mailing's recipients.

Parameters:
* mailing: Mailing ID
* before (optional): Timestamp; mailing must have been sent before
* after (optional): Timestamp; mailing must have been sent after

### `{{#linklogin-logins:before=|after=}}` (tbd)

Gets comma separated list of logins.

### `{{#linklogin-pref:}}`

Return list of Users with specific options. 

Parameters:
* option: WHERE Useroption is set and NOT empty
* option=false: WHERE Useroption is NOT set or empty
* option=value: WHERE Useroption is equal to value

### `{{#linklogin-ifuser:<true>|<false>}}`

Return first parameter, if the current user is a LinkLogin user or the second parameter if not.

### `{{#linklogin-pages:}}`

Return a list of all pages linked to a user.

Parameters:
* user (optional): user (default: current user)
* separator (optional): separator to be used for the list (default: `,`)

### `{{#linklogin-users:}}`

Return list of Users mapped to queried sites. 

Parameters:
* filter: Only return users with this filter
* group (optional): Only return users of this group


## Special Pages 

### Special:PopulateLoginLinks

Creates login hashes for all users who meet the conditions: they are in one of the LinkLogin groups, their e-mail address hasn't been set and the `user_email_token` and `user_email_token_expires` fields are empty or null. 

### Special:Mailings

Lists all mailings, allows to create, edit, and send mailings.

To change the columns shown in the detailed view for a mailing, use `MediaWiki:linklogin-columns`. By default all the preferences defined by `$wgLinkLoginPreferences` will be shown in their own column. If you define a column with no corresponding preference it is assumed, that a template should be used. In the template you can use all the preferences as variables.

### Special:EditMailing

Defines the form to create and edit mailings.

### Special:LoginLog

Shows successful logins.

### Special:LoginAttemptLog

Shows login attempts with invalid hashes.

### Special:LinkLogin Users

Lists all users for all the LinkLogin groups and allows to link them to pages.

### Special:LinkLogin Pages

Lists all the pages for all the categories associated with LinkLogin groups and allows to link them to users.


## Rights

### populateloginlinks

populate the user table with login links

### mailings

create and send mailings

### loginlogs

inspect login logs

### linklogin-link

link pages and users
