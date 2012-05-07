-- "New" storage location for history data.
CREATE TABLE /*_*/thread_history (
	th_id int NOT NULL auto_increment,
	th_thread int NOT NULL,
	
	th_timestamp varchar(14) NOT NULL,
	
	th_user int NOT NULL,
	th_user_text varchar(255) NOT NULL,
	
	th_change_type int NOT NULL,
	th_change_object int NOT NULL,
	th_change_comment text NOT NULL,
	
	-- Actual content, stored as a serialised thread row.
	th_content bytea NOT NULL,
	
	PRIMARY KEY (th_id),
	KEY (th_thread,th_timestamp),
	KEY (th_timestamp),
	KEY (th_user,th_user_text)
) /*$wgDBTableOptions*/;
