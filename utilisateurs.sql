DROP DATABASE IF EXISTS ent_multi_utilisateurs;

CREATE DATABASE ent_multi_utilisateurs;

USE ent_multi_utilisateurs;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL
    -- email VARCHAR(255) NOT NULL,
    -- registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
