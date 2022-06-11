DROP  INDEX user_message_state_unique;
ALTER TABLE  user_message_state ADD  PRIMARY KEY (ums_user, ums_thread);
