CREATE TABLE IF NOT EXISTS /*_*/ll_mailing (
  ll_mailing_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  ll_mailing_title varchar(255) binary NOT NULL DEFAULT '',
  ll_mailing_subject varchar(255) binary NOT NULL DEFAULT '',
  ll_mailing_template varchar(255) binary NOT NULL DEFAULT '',
  ll_mailing_loginpage varchar(255) binary NOT NULL DEFAULT '',
  ll_mailing_user integer unsigned NOT NULL,
  ll_mailing_group varbinary(255) NOT NULL,
  ll_mailing_timestamp varbinary(14) NOT NULL DEFAULT '',
  ll_mailing_archived varbinary(14) NOT NULL DEFAULT ''
) /*$wgDBTableOptions*/;