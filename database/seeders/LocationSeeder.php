<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Location::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ─── Nigeria States & Major LGAs ────────────────────────────────────────
        $nigeria = Location::create(['name' => 'Nigeria', 'type' => 'country', 'country_code' => 'NG', 'latitude' => 9.082, 'longitude' => 8.6753, 'timezone' => 'Africa/Lagos']);

        $nigeriaStates = [
            ['Abia',             'AB', 5.4527,  7.5248],
            ['Adamawa',          'AD', 9.3265,  12.3984],
            ['Akwa Ibom',        'AK', 5.0072,  7.8494],
            ['Anambra',          'AN', 6.2209,  7.0681],
            ['Bauchi',           'BA', 10.3158, 9.8442],
            ['Bayelsa',          'BY', 4.7719,  6.0699],
            ['Benue',            'BE', 7.3369,  8.7406],
            ['Borno',            'BO', 11.8333, 13.1500],
            ['Cross River',      'CR', 5.8702,  8.5988],
            ['Delta',            'DE', 5.8904,  5.6800],
            ['Ebonyi',           'EB', 6.2649,  8.0137],
            ['Edo',              'ED', 6.3350,  5.6037],
            ['Ekiti',            'EK', 7.7190,  5.3110],
            ['Enugu',            'EN', 6.4584,  7.5464],
            ['FCT Abuja',        'FC', 8.8940,  7.1860],
            ['Gombe',            'GO', 10.2791, 11.1670],
            ['Imo',              'IM', 5.4920,  7.0264],
            ['Jigawa',           'JI', 12.2280, 9.5616],
            ['Kaduna',           'KD', 10.5222, 7.4383],
            ['Kano',             'KN', 12.0022, 8.5920],
            ['Katsina',          'KT', 12.9816, 7.6166],
            ['Kebbi',            'KE', 12.4539, 4.1975],
            ['Kogi',             'KO', 7.8002,  6.7399],
            ['Kwara',            'KW', 8.9669,  4.3874],
            ['Lagos',            'LA', 6.5244,  3.3792],
            ['Nasarawa',         'NA', 8.4966,  8.5196],
            ['Niger',            'NI', 9.9309,  5.5983],
            ['Ogun',             'OG', 7.1600,  3.3497],
            ['Ondo',             'ON', 7.0926,  4.8200],
            ['Osun',             'OS', 7.5629,  4.5200],
            ['Oyo',              'OY', 7.8500,  3.9300],
            ['Plateau',          'PL', 9.2182,  9.5176],
            ['Rivers',           'RI', 4.8396,  7.0333],
            ['Sokoto',           'SO', 13.0059, 5.2476],
            ['Taraba',           'TA', 7.9993,  9.9996],
            ['Yobe',             'YO', 12.2939, 11.7398],
            ['Zamfara',          'ZA', 12.1221, 6.2236],
        ];

        // Major cities per state
        $stateCities = [
            'Lagos' => ['Lagos Island', 'Ikeja', 'Surulere', 'Victoria Island', 'Lekki', 'Yaba', 'Alimosho', 'Badagry', 'Ikorodu', 'Eti-Osa'],
            'FCT Abuja' => ['Abuja Municipal', 'Gwagwalada', 'Kuje', 'Bwari', 'Abaji', 'Kwali', 'Maitama', 'Garki', 'Wuse', 'Asokoro'],
            'Kano' => ['Kano Municipal', 'Fagge', 'Dala', 'Gwale', 'Kumbotso', 'Nassarawa', 'Tarauni', 'Ungogo'],
            'Rivers' => ['Port Harcourt', 'Obio/Akpor', 'Okrika', 'Bonny', 'Eleme', 'Ogu/Bolo'],
            'Oyo' => ['Ibadan North', 'Ibadan South', 'Ogbomosho', 'Oyo East', 'Iseyin'],
            'Anambra' => ['Awka', 'Onitsha North', 'Onitsha South', 'Nnewi', 'Ekwusigo'],
            'Enugu' => ['Enugu North', 'Enugu South', 'Igbo-Eze North', 'Udi', 'Nkanu'],
            'Kaduna' => ['Kaduna North', 'Kaduna South', 'Zaria', 'Kafanchan', 'Chikun'],
            'Delta' => ['Warri', 'Asaba', 'Uvwie', 'Oshimili South', 'Sapele'],
            'Edo' => ['Benin City', 'Egor', 'Ikpoba-Okha', 'Oredo', 'Ovia North-East'],
        ];

        foreach ($nigeriaStates as [$stateName, $code, $lat, $lng]) {
            $state = Location::create([
                'name' => $stateName,
                'type' => 'state',
                'parent_id' => $nigeria->id,
                'country_code' => 'NG',
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => 'Africa/Lagos',
            ]);

            // Add cities for this state if defined
            $cities = $stateCities[$stateName] ?? [];
            foreach ($cities as $cityName) {
                Location::create([
                    'name' => $cityName,
                    'type' => 'city',
                    'parent_id' => $state->id,
                    'country_code' => 'NG',
                    'timezone' => 'Africa/Lagos',
                ]);
            }
        }

        // ─── West Africa Countries ───────────────────────────────────────────────
        $westAfrica = [
            ['Ghana',        'GH', 7.9465,  -1.0232, 'Africa/Accra',    ['Accra', 'Kumasi', 'Tamale', 'Sekondi-Takoradi', 'Cape Coast']],
            ['Senegal',      'SN', 14.4974, -14.4524, 'Africa/Dakar',    ['Dakar', 'Thiès', 'Saint-Louis', 'Ziguinchor']],
            ['Ivory Coast',  'CI', 7.5400,  -5.5471, 'Africa/Abidjan',  ['Abidjan', 'Bouaké', 'Yamoussoukro', 'Daloa', 'San-Pédro']],
            ['Cameroon',     'CM', 3.8480,  11.5021, 'Africa/Douala',   ['Douala', 'Yaoundé', 'Bamenda', 'Bafoussam', 'Garoua']],
            ['Mali',         'ML', 17.5707, -3.9962, 'Africa/Bamako',   ['Bamako', 'Sikasso', 'Mopti', 'Koutiala']],
            ['Burkina Faso', 'BF', 12.3642, -1.5330, 'Africa/Ouagadougou', ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou']],
            ['Niger',        'NE', 17.6078, 8.0817,  'Africa/Niamey',   ['Niamey', 'Zinder', 'Maradi', 'Tahoua']],
            ['Guinea',       'GN', 9.9456,  -11.2787, 'Africa/Conakry',  ['Conakry', 'Nzérékoré', 'Kindia', 'Labé']],
            ['Benin',        'BJ', 9.3077,  2.3158,  'Africa/Porto-Novo', ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey']],
            ['Togo',         'TG', 8.6195,  0.8248,  'Africa/Lome',     ['Lomé', 'Kpalimé', 'Atakpamé', 'Sokodé']],
            ['Sierra Leone', 'SL', 8.4606,  -11.7799, 'Africa/Freetown', ['Freetown', 'Bo', 'Kenema', 'Makeni']],
            ['Liberia',      'LR', 6.4281,  -9.4295, 'Africa/Monrovia', ['Monrovia', 'Gbarnga', 'Buchanan', 'Voinjama']],
            ['Gambia',       'GM', 13.4432, -15.3101, 'Africa/Banjul',   ['Banjul', 'Serekunda', 'Brikama', 'Bakau']],
            ['Guinea-Bissau', 'GW', 11.8037, -15.1804, 'Africa/Bissau',   ['Bissau', 'Bafatá', 'Gabú']],
            ['Mauritania',   'MR', 21.0079, -10.9408, 'Africa/Nouakchott', ['Nouakchott', 'Nouadhibou', 'Rosso']],
            ['Cape Verde',   'CV', 16.0020, -24.0132, 'Atlantic/Cape_Verde', ['Praia', 'Mindelo', 'Santa Maria']],
        ];

        foreach ($westAfrica as [$countryName, $code, $lat, $lng, $tz, $cities]) {
            $country = Location::create([
                'name' => $countryName,
                'type' => 'country',
                'country_code' => $code,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
            ]);

            foreach ($cities as $cityName) {
                Location::create([
                    'name' => $cityName,
                    'type' => 'city',
                    'parent_id' => $country->id,
                    'country_code' => $code,
                    'timezone' => $tz,
                ]);
            }
        }

        // ─── Rest of Africa & Major World Countries ──────────────────────────────
        $worldCountries = [
            // Africa (non-west)
            ['South Africa', 'ZA',  -30.5595, 22.9375,  'Africa/Johannesburg', ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria']],
            ['Kenya',        'KE',   -0.0236,  37.9062,  'Africa/Nairobi',      ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru']],
            ['Ethiopia',     'ET',    9.1450,  40.4897,  'Africa/Addis_Ababa',  ['Addis Ababa', 'Dire Dawa', 'Mekelle']],
            ['Egypt',        'EG',   26.8206,  30.8025,  'Africa/Cairo',        ['Cairo', 'Alexandria', 'Giza', 'Shubra El Kheima']],
            ['Tanzania',     'TZ',   -6.3690,  34.8888,  'Africa/Dar_es_Salaam', ['Dar es Salaam', 'Zanzibar City', 'Arusha', 'Dodoma']],
            ['Rwanda',       'RW',   -1.9403,  29.8739,  'Africa/Kigali',       ['Kigali', 'Butare', 'Gisenyi']],
            ['Uganda',       'UG',    1.3733,  32.2903,  'Africa/Kampala',      ['Kampala', 'Entebbe', 'Gulu']],
            // Americas
            ['United States', 'US',   37.0902, -95.7129,  'America/New_York',    ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix']],
            ['United Kingdom', 'GB',  55.3781,  -3.4360,  'Europe/London',       ['London', 'Birmingham', 'Manchester', 'Leeds', 'Glasgow']],
            ['Canada',       'CA',   56.1304, -106.3468, 'America/Toronto',     ['Toronto', 'Vancouver', 'Montreal', 'Calgary', 'Ottawa']],
            ['Brazil',       'BR',  -14.2350, -51.9253,  'America/Sao_Paulo',   ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador']],
            // Europe
            ['France',       'FR',   46.2276,   2.2137,  'Europe/Paris',        ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice']],
            ['Germany',      'DE',   51.1657,  10.4515,  'Europe/Berlin',       ['Berlin', 'Hamburg', 'Munich', 'Cologne', 'Frankfurt']],
            // Asia
            ['India',        'IN',   20.5937,  78.9629,  'Asia/Kolkata',        ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai']],
            ['China',        'CN',   35.8617, 104.1954,  'Asia/Shanghai',       ['Shanghai', 'Beijing', 'Guangzhou', 'Shenzhen', 'Chengdu']],
            ['UAE',          'AE',   23.4241,  53.8478,  'Asia/Dubai',          ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman']],
            // Oceania
            ['Australia',    'AU',  -25.2744, 133.7751,  'Australia/Sydney',    ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide']],
        ];

        foreach ($worldCountries as [$countryName, $code, $lat, $lng, $tz, $cities]) {
            $country = Location::create([
                'name' => $countryName,
                'type' => 'country',
                'country_code' => $code,
                'latitude' => $lat,
                'longitude' => $lng,
                'timezone' => $tz,
            ]);

            foreach ($cities as $cityName) {
                Location::create([
                    'name' => $cityName,
                    'type' => 'city',
                    'parent_id' => $country->id,
                    'country_code' => $code,
                    'timezone' => $tz,
                ]);
            }
        }

        $this->command->info('Locations seeded: Nigeria (states + cities), West Africa, and world countries.');
    }
}
