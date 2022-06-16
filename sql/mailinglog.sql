CREATE TABLE IF NOT EXISTS /*_*/ll_mailinglog (
  ll_mailinglog_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  ll_mailinglog_mailing integer unsigned NOT NULL,
  ll_mailinglog_user integer unsigned NOT NULL,
  ll_mailinglog_timestamp varbinary(14) NOT NULL DEFAULT ''
) /*$wgDBTableOptions*/;