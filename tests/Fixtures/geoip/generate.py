"""Database GeoLite2 finti per i test (City e ASN), con pochi indirizzi di esempio.

    pip install mmdb-writer netaddr
    python3 tests/Fixtures/geoip/generate.py
"""
from pathlib import Path

from mmdb_writer import MMDBWriter
from netaddr import IPSet

here = Path(__file__).parent

city = MMDBWriter(ip_version=6, database_type='GeoLite2-City', languages=['en'], description={'en': 'Resume App test data'}, ipv4_compatible=True)
city.insert_network(IPSet(['93.44.0.0/16']), {
    'country': {'iso_code': 'IT', 'names': {'en': 'Italy'}},
    'subdivisions': [{'iso_code': '25', 'names': {'en': 'Lombardy'}}],
    'city': {'names': {'en': 'Milan'}},
    'location': {'latitude': 45.4643, 'longitude': 9.1895, 'accuracy_radius': 20},
})
city.insert_network(IPSet(['81.2.69.0/24']), {
    'country': {'iso_code': 'GB', 'names': {'en': 'United Kingdom'}},
    'city': {'names': {'en': 'London'}},
    'location': {'latitude': 51.5142, 'longitude': -0.0931, 'accuracy_radius': 20},
})
city.insert_network(IPSet(['2a01:4f8::/32']), {
    'country': {'iso_code': 'DE', 'names': {'en': 'Germany'}},
    'subdivisions': [{'iso_code': 'BY', 'names': {'en': 'Bavaria'}}],
    'city': {'names': {'en': 'Nuremberg'}},
    'location': {'latitude': 49.4478, 'longitude': 11.0683, 'accuracy_radius': 50},
})
city.to_db_file(str(here / 'GeoLite2-City-Test.mmdb'))

asn = MMDBWriter(ip_version=6, database_type='GeoLite2-ASN', languages=['en'], description={'en': 'Resume App test data'}, ipv4_compatible=True)
asn.insert_network(IPSet(['93.44.0.0/16']), {'autonomous_system_number': 3269, 'autonomous_system_organization': 'Telecom Italia'})
asn.insert_network(IPSet(['2a01:4f8::/32']), {'autonomous_system_number': 24940, 'autonomous_system_organization': 'Hetzner Online GmbH'})
asn.to_db_file(str(here / 'GeoLite2-ASN-Test.mmdb'))
