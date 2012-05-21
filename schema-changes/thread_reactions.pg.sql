-- Storage for reactions
CREATE TABLE /*_*/thread_reaction (
	tr_thread int NOT NULL,
	tr_user int NOT NULL,
	tr_user_text varchar(255) NOT NULL,
	tr_type varchar(64) NOT NULL,
	tr_value int NOT NULL,
	
	PRIMARY KEY (tr_thread,tr_user,tr_user_text,tr_type,tr_value)
) /*$wgDBTableOptions*/;
CREATE INDEX thread_reaction_user_text_value ON thread_reaction (tr_user,tr_user_text,tr_type,tr_value);
