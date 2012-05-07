-- Storage for "pending" relationships from import
CREATE TABLE /*_*/thread_pending_relationship (
	tpr_thread int NOT NULL,
	tpr_relationship varchar(64) NOT NULL,
	tpr_title varchar(255) NOT NULL,
	tpr_type varchar(32) NOT NULL,
	PRIMARY KEY (tpr_thread,tpr_relationship)
) /*$wgDBTableOptions*/;
