CREATE TABLE IF NOT EXISTS /*_*/ll_mapping (
  ll_mapping_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  ll_mapping_user integer unsigned NOT NULL,
  ll_mapping_page integer unsigned NOT NULL
) /*$wgDBTableOptions*/;