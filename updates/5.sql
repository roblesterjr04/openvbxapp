CREATE TABLE installs
(
	id BIGINT AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	url_prefix VARCHAR(255) NOT NULL,
	local_prefix VARCHAR(1000) NOT NULL,
	PRIMARY KEY(id),
	INDEX(name),
	INDEX url_prefix (url_prefix)
) ENGINE=InnoDB CHARSET=UTF8;

CREATE TABLE settings
(
	id BIGINT AUTO_INCREMENT,	
	install_id BIGINT NOT NULL,
	name VARCHAR(32) NOT NULL,
	value VARCHAR(255) NOT NULL,
	PRIMARY KEY(id),
	INDEX(name),
	INDEX(install_id, name)
) ENGINE=InnoDB CHARSET=UTF8;


ALTER TABLE annotation_types ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE annotations ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE audio ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE flows ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE group_messages ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE groups ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE labels ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE messages ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE numbers ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE rest_access ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE user_labels ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE user_messages ENGINE=InnoDB CHARSET=UTF8;
ALTER TABLE users ENGINE=InnoDB CHARSET=UTF8;

ALTER TABLE settings ADD FOREIGN KEY(install_id) REFERENCES installs(id);

ALTER TABLE user_messages ADD FOREIGN KEY(user_id) REFERENCES users(id);
ALTER TABLE user_messages ADD FOREIGN KEY(message_id) REFERENCES messages(id);

ALTER TABLE user_labels ADD FOREIGN KEY(user_id) REFERENCES users(id);
ALTER TABLE user_labels ADD FOREIGN KEY(label_id) REFERENCES labels(id);

ALTER TABLE group_messages ADD FOREIGN KEY(group_id) REFERENCES groups(id);
ALTER TABLE group_messages ADD FOREIGN KEY(message_id) REFERENCES messages(id);

ALTER TABLE numbers ADD FOREIGN KEY(user_id) REFERENCES users(id);
ALTER TABLE flows ADD FOREIGN KEY(user_id) REFERENCES users(id);
