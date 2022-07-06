DROP  INDEX historical_thread_unique;
ALTER TABLE  historical_thread ADD  PRIMARY KEY (hthread_id, hthread_revision);
