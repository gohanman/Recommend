# Recommend
Graph Database ETL

### Getting Started
```
$ composer install
$ cp config.json.dist config.json
$ ./recommend.php YYYY-MM-DD YYYY-MM-DD
```

### Configuration
The tool needs to know:
* How to connect and retrieve data from the source database
* How to connect to Neo4j

The default configuration assumes user-provided scripts to retrieve source data. A CORE-POS driver is also provided. A CORE-POS configuration might look like this:

```json
{
    "neo4j": {
        "host": "localhost",
        "user": "neo4j",
        "password": "neo4j"
    },
    "driver": {
        "name": "COREPOS\\Recommend\\Driver\\CoreDriver",
        "options": {
            "fanniePath": "/var/www/html/IS4C/",
            "exclude": {
                "members": [99999],
                "types": [0]
            }
        }
    }
}
```

### Results
The tool creates two types of nodes in the graph database, People and Items. These nodes are then connected by PURCHASED relationships.
