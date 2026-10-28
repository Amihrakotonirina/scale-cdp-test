CREATE DATABASE IF NOT EXISTS cdp_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE cdp_test;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    name VARCHAR(200) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT fk_events_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE CASCADE,
    KEY idx_events_customer_time (customer_id, occurred_at DESC, id DESC),
    KEY idx_events_name_time_customer (event_name, occurred_at, customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_properties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    property_name VARCHAR(100) NOT NULL,
    value_type ENUM('number', 'string', 'boolean') NOT NULL,
    value_number DECIMAL(20, 6) NULL,
    value_string VARCHAR(1000) NULL,
    value_boolean TINYINT(1) NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_event_properties_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uq_event_property (event_id, property_name),
    KEY idx_property_number_event (property_name, value_number, event_id),
    KEY idx_property_string_event (property_name, value_string(191), event_id),
    KEY idx_property_boolean_event (property_name, value_boolean, event_id)
) ENGINE=InnoDB;