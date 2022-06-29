create table employees (
	name        varchar(200) null,
	dnrs        varchar(100) null,
	url         varchar(200) null,
	employee_id varchar(100) not null primary key,
	fetched     int          null,
	updated_at  datetime     null,
	created_at  datetime     null
);

create table locations (
	shortlabel varchar(50)  not null primary key,
	label      varchar(100) null,
	address    varchar(200) null,
	lat        float        null,
	`long`     float        null
);

create table logs (
	id         int unsigned auto_increment primary key,
	`key`      varchar(255) not null,
	html       longtext     not null,
	vevent     longtext     not null,
	created_at timestamp    null,
	updated_at timestamp    null
	);

create table users (
	username        varchar(100) not null primary key,
	healthcheckuuid varchar(100) null
);