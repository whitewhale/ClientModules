This module is designed to implement a widget that mimics the core LiveWhale widgets, but permits direct translation of priprietary DB data to a content type for display.

Instructions:

- After installing this module, you must create pre-defined data sources. When creating a new widget of this type, you will be prompted to choose from this list of data sources for your widget's output.
- Sample data sources have been provided in /includes/sources. The "livewhale" data source provides out-of-the-box access to the core LiveWhale tables. The "sample-mysql-type" provides an example of how you would create a data source for a standard MySQL db.
- The /includes/plugins directory contains plugins for which engines a custom data source can utilize. Support is provided out-of-the-box for MySQL (+ MariaDB, etc.), and Oracle.
- Once a data source has been created, visit the widget editor to get started.