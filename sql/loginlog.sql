CREATE TABLE IF NOT EXISTS /*_*/ll_loginlog (
  ll_loginlog_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  ll_loginlog_user integer unsigned NOT NULL,
  ll_loginlog_hash varbinary(255) NOT NULL,
  ll_loginlog_timestamp varbinary(14) NOT NULL
) /*$wgDBTableOptions*/;