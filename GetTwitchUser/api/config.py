from configparser import ConfigParser
import os

def config(filename="database.ini", section="postgresq1"):
    # Construct the full path to the database.ini file
    filepath = os.path.join(os.path.dirname(__file__), filename)
    # Create a parser
    parser = ConfigParser()
    # Read config file
    parser.read(filepath)

    # Create a dictionary to hold the parameters
    db = {}
    if parser.has_section(section):
        params = parser.items(section)
        for param in params:
            db[param[0]] = param[1]
    else:
        raise Exception('Section {0} is not found in the {1} file.'.format(section, filename))
    
    return db
