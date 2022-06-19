CREATE TABLE IF NOT EXISTS /*_*/ll_attemptlog (
  ll_attemptlog_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  ll_attemptlog_ip varbinary(255) NOT NULL,
  ll_attemptlog_hash varbinary(255) NOT NULL,
  ll_attemptlog_timestamp varbinary(14) NOT NULL
) /*$wgDBTableOptions*/;