<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NigerianInstitutionSeeder extends Seeder
{
    /**
     * Seed Nigerian universities, polytechnics, and colleges of education
     * into the organizations table (type = 'university' | 'polytechnic' | 'college').
     */
    public function run(): void
    {
        // Truncate only institution-type records so other orgs are preserved
        DB::table('organizations')
            ->whereIn('type', ['university', 'polytechnic', 'college'])
            ->delete();

        $institutions = [];

        // ─── Federal Universities ────────────────────────────────────────────────
        $federalUniversities = [
            ['Abubakar Tafawa Balewa University',                     'abn.edu.ng',        'Bauchi'],
            ['Ahmadu Bello University',                               'abu.edu.ng',        'Zaria'],
            ['Bayero University, Kano',                               'buk.edu.ng',        'Kano'],
            ['Federal University Birnin Kebbi',                      'fubk.edu.ng',       'Kebbi'],
            ['Federal University Dutse',                             'fud.edu.ng',        'Jigawa'],
            ['Federal University Dutsin-Ma',                         'fudma.edu.ng',      'Katsina'],
            ['Federal University Gashua',                            'fugashua.edu.ng',   'Yobe'],
            ['Federal University Gusau',                             'fugusau.edu.ng',    'Zamfara'],
            ['Federal University Kashere',                           'fukashere.edu.ng',  'Gombe'],
            ['Federal University Lafia',                             'fulafia.edu.ng',    'Nasarawa'],
            ['Federal University Lokoja',                            'fulokoja.edu.ng',   'Kogi'],
            ['Federal University Ndufu-Alike Ikwo',                  'funai.edu.ng',      'Ebonyi'],
            ['Federal University Otuoke',                            'fuotuoke.edu.ng',   'Bayelsa'],
            ['Federal University Oye-Ekiti',                         'fuoye.edu.ng',      'Ekiti'],
            ['Federal University Wukari',                            'fuwukari.edu.ng',   'Taraba'],
            ['Federal University of Agriculture, Abeokuta',          'funaab.edu.ng',     'Ogun'],
            ['Federal University of Agriculture, Makurdi',           'uam.edu.ng',        'Benue'],
            ['Federal University of Petroleum Resources, Effurun',   'fupre.edu.ng',      'Delta'],
            ['Federal University of Technology, Akure',              'futa.edu.ng',       'Ondo'],
            ['Federal University of Technology, Minna',              'futminna.edu.ng',   'Niger'],
            ['Federal University of Technology, Owerri',             'futo.edu.ng',       'Imo'],
            ['Modibbo Adama University of Technology',               'mautech.edu.ng',    'Adamawa'],
            ['National Open University of Nigeria',                   'nou.edu.ng',        'FCT Abuja'],
            ['Nigeria Police Academy',                               'polac.edu.ng',      'Wudil'],
            ['Nnamdi Azikiwe University',                            'unizik.edu.ng',     'Anambra'],
            ['Obafemi Awolowo University',                           'oauife.edu.ng',     'Osun'],
            ['University of Abuja',                                  'uniabuja.edu.ng',   'FCT Abuja'],
            ['University of Benin',                                  'uniben.edu.ng',     'Edo'],
            ['University of Calabar',                                'unical.edu.ng',     'Cross River'],
            ['University of Ibadan',                                 'ui.edu.ng',         'Oyo'],
            ['University of Ilorin',                                 'unilorin.edu.ng',   'Kwara'],
            ['University of Jos',                                    'unijos.edu.ng',     'Plateau'],
            ['University of Lagos',                                  'unilag.edu.ng',     'Lagos'],
            ['University of Maiduguri',                              'unimaid.edu.ng',    'Borno'],
            ['University of Nigeria Nsukka',                         'unn.edu.ng',        'Enugu'],
            ['University of Port Harcourt',                          'uniport.edu.ng',    'Rivers'],
            ['University of Uyo',                                    'uniuyo.edu.ng',     'Akwa Ibom'],
            ['Usmanu Danfodiyo University',                          'udusok.edu.ng',     'Sokoto'],
            ['Michael Okpara University of Agriculture, Umudike',    'mouau.edu.ng',      'Abia'],
        ];

        foreach ($federalUniversities as [$name, $domain, $state]) {
            $institutions[] = [
                'name' => $name,
                'type' => 'university',
                'domain' => $domain,
                'contact_email' => 'info@'.$domain,
                'status' => 'inactive',
                'subscription_plan' => 'basic',
                'max_members' => 500,
                'sso_config' => json_encode(['state' => $state, 'category' => 'federal']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ─── State Universities ──────────────────────────────────────────────────
        $stateUniversities = [
            ['Adekunle Ajasin University',                          'aaua.edu.ng',       'Ondo'],
            ['Ambrose Alli University',                             'aauekpoma.edu.ng',  'Edo'],
            ['Anambra State University',                            'ansu.edu.ng',       'Anambra'],
            ['Benue State University',                              'bsum.edu.ng',       'Benue'],
            ['Cross River University of Technology',               'crutech.edu.ng',    'Cross River'],
            ['Delta State University, Abraka',                     'delsu.edu.ng',      'Delta'],
            ['Ebonyi State University',                            'ebsu.edu.ng',       'Ebonyi'],
            ['Ekiti State University',                             'eksu.edu.ng',       'Ekiti'],
            ['Enugu State University of Science and Technology',   'esut.edu.ng',       'Enugu'],
            ['Imo State University',                               'imsu.edu.ng',       'Imo'],
            ['Kaduna State University',                            'kasu.edu.ng',       'Kaduna'],
            ['Kogi State University',                              'ksu.edu.ng',        'Kogi'],
            ['Kwara State University',                             'kwasu.edu.ng',      'Kwara'],
            ['Lagos State University',                             'lasu.edu.ng',       'Lagos'],
            ['Nasarawa State University',                          'nsuk.edu.ng',       'Nasarawa'],
            ['Niger Delta University',                             'ndu.edu.ng',        'Bayelsa'],
            ['Olabisi Onabanjo University',                        'oouagoiwoye.edu.ng', 'Ogun'],
            ['Ondo State University of Medical Sciences',          'osums.edu.ng',      'Ondo'],
            ['Osun State University',                              'uniosun.edu.ng',    'Osun'],
            ['Oyo State Technical University',                     'tech-u.edu.ng',     'Oyo'],
            ['Plateau State University',                           'plasu.edu.ng',      'Plateau'],
            ['Redeemer\'s University',                             'run.edu.ng',        'Osun'],
            ['Rivers State University',                            'ust.edu.ng',        'Rivers'],
            ['Sokoto State University',                            'ssu.edu.ng',        'Sokoto'],
            ['Tai Solarin University of Education',                'tasued.edu.ng',     'Ogun'],
            ['Taraba State University',                            'tsuniversity.edu.ng', 'Taraba'],
            ['Umaru Musa Yar\'Adua University',                    'umyu.edu.ng',       'Katsina'],
            ['University of Africa Toru-Orua',                    'uat.edu.ng',        'Bayelsa'],
            ['University of Medical Sciences, Ondo',              'unimed.edu.ng',     'Ondo'],
            ['Yobe State University',                              'ysu.edu.ng',        'Yobe'],
            ['Zamfara State University',                           'zamfsu.edu.ng',     'Zamfara'],
        ];

        foreach ($stateUniversities as [$name, $domain, $state]) {
            $institutions[] = [
                'name' => $name,
                'type' => 'university',
                'domain' => $domain,
                'contact_email' => 'info@'.$domain,
                'status' => 'inactive',
                'subscription_plan' => 'basic',
                'max_members' => 300,
                'sso_config' => json_encode(['state' => $state, 'category' => 'state']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ─── Private Universities ────────────────────────────────────────────────
        $privateUniversities = [
            ['Achievers University',                          'achievers.edu.ng',      'Ondo'],
            ['African University of Science and Technology', 'aust.edu.ng',           'FCT Abuja'],
            ['Afe Babalola University',                      'abuad.edu.ng',          'Ekiti'],
            ['American University of Nigeria',               'aun.edu.ng',            'Adamawa'],
            ['Augustine University',                         'augustineuniversity.edu.ng', 'Lagos'],
            ['Babcock University',                           'babcock.edu.ng',         'Ogun'],
            ['Bells University of Technology',               'bellsuniversity.edu.ng', 'Ogun'],
            ['Benson Idahosa University',                    'biu.edu.ng',            'Edo'],
            ['Bowen University',                             'bowenuniversity.edu.ng', 'Osun'],
            ['Caritas University',                           'caritasuni.edu.ng',      'Enugu'],
            ['Covenant University',                          'covenantuniversity.edu.ng', 'Ogun'],
            ['Crawford University',                          'crawforduniversity.edu.ng', 'Ogun'],
            ['Crescent University',                          'crescent-university.edu.ng', 'Ogun'],
            ['Fountain University',                          'fountainuniversity.edu.ng', 'Osun'],
            ['Godfrey Okoye University',                     'gouni.edu.ng',           'Enugu'],
            ['Gregory University',                           'gregoryuniversityuturu.edu.ng', 'Abia'],
            ['Hallmark University',                          'hallmarkuniversity.edu.ng', 'Ogun'],
            ['Joseph Ayo Babalola University',               'jabu.edu.ng',            'Ekiti'],
            ['Kings University',                             'kingsuniversity.edu.ng', 'Osun'],
            ['Landmark University',                          'lmu.edu.ng',             'Kwara'],
            ['Lead City University',                         'lcu.edu.ng',             'Oyo'],
            ['Madonna University',                           'madonnauniversity.edu.ng', 'Anambra'],
            ['Mcpherson University',                         'mcphersonuniversity.edu.ng', 'Ogun'],
            ['Pan-Atlantic University',                      'pau.edu.ng',             'Lagos'],
            ['Paul University',                              'pauluniversity.edu.ng',  'Anambra'],
            ['Salem University',                             'salemuniversity.edu.ng', 'Kogi'],
            ['Southwestern University Nigeria',              'southwesternuniversity.edu.ng', 'Ogun'],
            ['Veritas University',                           'veritas.edu.ng',         'FCT Abuja'],
            ['Wellspring University',                        'wellspringuniversity.edu.ng', 'Edo'],
            ['Wesley University',                            'wesleyuniversityowo.edu.ng', 'Ondo'],
        ];

        foreach ($privateUniversities as [$name, $domain, $state]) {
            $institutions[] = [
                'name' => $name,
                'type' => 'university',
                'domain' => $domain,
                'contact_email' => 'info@'.$domain,
                'status' => 'inactive',
                'subscription_plan' => 'basic',
                'max_members' => 200,
                'sso_config' => json_encode(['state' => $state, 'category' => 'private']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ─── Federal Polytechnics ────────────────────────────────────────────────
        $federalPolytechnics = [
            ['Auchi Polytechnic',                                    'auchipoly.edu.ng',  'Edo'],
            ['Federal Polytechnic Ado-Ekiti',                        'fedpolyado.edu.ng', 'Ekiti'],
            ['Federal Polytechnic Bali',                             'fedpolybali.edu.ng', 'Taraba'],
            ['Federal Polytechnic Bauchi',                           'federalpolybauchi.edu.ng', 'Bauchi'],
            ['Federal Polytechnic Bida',                             'fedpolybida.edu.ng', 'Niger'],
            ['Federal Polytechnic Damaturu',                         'fedpolydamaturu.edu.ng', 'Yobe'],
            ['Federal Polytechnic Idah',                             'fedpolyidah.edu.ng', 'Kogi'],
            ['Federal Polytechnic Ile-Oluji',                        'fpi.edu.ng',        'Ondo'],
            ['Federal Polytechnic Ilaro',                            'federalpolyilaro.edu.ng', 'Ogun'],
            ['Federal Polytechnic Kaura Namoda',                     'fedpolykn.edu.ng',  'Zamfara'],
            ['Federal Polytechnic Mubi',                             'fedpolymubi.edu.ng', 'Adamawa'],
            ['Federal Polytechnic Nasarawa',                         'fedpolynasarawa.edu.ng', 'Nasarawa'],
            ['Federal Polytechnic Nekede',                           'fpno.edu.ng',       'Imo'],
            ['Federal Polytechnic Offa',                             'fedpolyoffa.edu.ng', 'Kwara'],
            ['Federal Polytechnic Oko',                              'fedpolyoko.edu.ng', 'Anambra'],
            ['Federal Polytechnic Ukana',                            'fedpolyukana.edu.ng', 'Akwa Ibom'],
            ['Kaduna Polytechnic',                                   'kadpoly.edu.ng',    'Kaduna'],
            ['Rufus Giwa Polytechnic',                               'rugipo.edu.ng',     'Ondo'],
            ['Waziri Umaru Federal Polytechnic',                     'wufpby.edu.ng',     'Kebbi'],
            ['Yaba College of Technology',                           'yabatech.edu.ng',   'Lagos'],
        ];

        foreach ($federalPolytechnics as [$name, $domain, $state]) {
            $institutions[] = [
                'name' => $name,
                'type' => 'polytechnic',
                'domain' => $domain,
                'contact_email' => 'info@'.$domain,
                'status' => 'inactive',
                'subscription_plan' => 'basic',
                'max_members' => 300,
                'sso_config' => json_encode(['state' => $state, 'category' => 'federal']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ─── State Polytechnics ──────────────────────────────────────────────────
        $statePolytechnics = [
            ['Akanu Ibiam Federal Polytechnic Unwana',       'aifpu.edu.ng',  'Ebonyi'],
            ['Delta State Polytechnic Ogwashi-Uku',          'dspg.edu.ng',   'Delta'],
            ['Hassan Usman Katsina Polytechnic',             'hukpoly.edu.ng', 'Katsina'],
            ['Hussaini Adamu Federal Polytechnic',           'hafedpoly.edu.ng', 'Jigawa'],
            ['Institute of Management and Technology Enugu', 'imtugep.edu.ng', 'Enugu'],
            ['Imo State Polytechnic',                        'imopoly.edu.ng', 'Imo'],
            ['Kano State Polytechnic',                       'kanopoly.edu.ng', 'Kano'],
            ['Kwara State Polytechnic',                      'kwarapoly.edu.ng', 'Kwara'],
            ['Lagos State Polytechnic',                      'laspotech.edu.ng', 'Lagos'],
            ['Moshood Abiola Polytechnic',                   'mapoly.edu.ng', 'Ogun'],
            ['Osun State College of Technology',             'oscotech.edu.ng', 'Osun'],
            ['Plateau State Polytechnic',                    'plapoly.edu.ng', 'Plateau'],
            ['Rivers State Polytechnic',                     'poly.edu.ng',   'Rivers'],
        ];

        foreach ($statePolytechnics as [$name, $domain, $state]) {
            $institutions[] = [
                'name' => $name,
                'type' => 'polytechnic',
                'domain' => $domain,
                'contact_email' => 'info@'.$domain,
                'status' => 'inactive',
                'subscription_plan' => 'basic',
                'max_members' => 200,
                'sso_config' => json_encode(['state' => $state, 'category' => 'state']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ─── Colleges of Education ───────────────────────────────────────────────
        $collegesOfEducation = [
            ['Adeniran Ogunsanya College of Education',     'aocoed.edu.ng',    'Lagos'],
            ['Adeyemi Federal University of Education',     'adufe.edu.ng',     'Ondo'],
            ['Alvan Ikoku Federal University of Education', 'alvanfce.edu.ng',  'Imo'],
            ['College of Education, Agbor',                 'coeagbor.edu.ng',  'Delta'],
            ['College of Education, Ekiadolor-Benin',       'coebenin.edu.ng',  'Edo'],
            ['College of Education, Gindiri',               'coegindiri.edu.ng', 'Plateau'],
            ['College of Education, Ila-Orangun',           'coeilaoran.edu.ng', 'Osun'],
            ['College of Education, Kankara',               'coekankara.edu.ng', 'Katsina'],
            ['College of Education, Warri',                 'coewarri.edu.ng',  'Delta'],
            ['College of Education, Zuba',                  'coezuba.edu.ng',   'FCT Abuja'],
            ['Federal College of Education, Abeokuta',      'fceabeokuta.edu.ng', 'Ogun'],
            ['Federal College of Education, Eha-Amufu',    'fceeha.edu.ng',    'Enugu'],
            ['Federal College of Education, Gombe',        'fcegombe.edu.ng',  'Gombe'],
            ['Federal College of Education, Kano',         'fcekano.edu.ng',   'Kano'],
            ['Federal College of Education, Katsina',      'fcekatsina.edu.ng', 'Katsina'],
            ['Federal College of Education, Kontagora',    'fcekontagora.edu.ng', 'Niger'],
            ['Federal College of Education, Okene',        'fceokene.edu.ng',  'Kogi'],
            ['Federal College of Education, Obudu',        'fceobudu.edu.ng',  'Cross River'],
            ['Federal College of Education, Oyo',          'fceoyo.edu.ng',    'Oyo'],
            ['Federal College of Education, Pankshin',     'fcepankshin.edu.ng', 'Plateau'],
            ['Federal College of Education, Potiskum',     'fcepotiskum.edu.ng', 'Yobe'],
            ['Federal College of Education, Umunze',       'fceumunze.edu.ng', 'Anambra'],
            ['Federal College of Education, Yola',         'fceyola.edu.ng',   'Adamawa'],
            ['Federal College of Education, Zaria',        'fcezaria.edu.ng',  'Kaduna'],
            ['Federal College of Education (Tech) Akoka',  'fcetakoka.edu.ng', 'Lagos'],
            ['Federal College of Education (Tech) Asaba',  'fcetasaba.edu.ng', 'Delta'],
            ['Federal College of Education (Tech) Bichi',  'fcetbichi.edu.ng', 'Kano'],
            ['Federal College of Education (Tech) Gombe',  'fcetgombe.edu.ng', 'Gombe'],
            ['Federal College of Education (Tech) Omoku',  'fcetomoku.edu.ng', 'Rivers'],
            ['Federal College of Education (Tech) Potiskum', 'fcetpotiskum.edu.ng', 'Yobe'],
            ['Federal College of Education (Tech) Umunze', 'fcetumunze.edu.ng', 'Anambra'],
            ['Tai Solarin College of Education',            'tasce.edu.ng',     'Ogun'],
        ];

        foreach ($collegesOfEducation as [$name, $domain, $state]) {
            $institutions[] = [
                'name' => $name,
                'type' => 'college',
                'domain' => $domain,
                'contact_email' => 'info@'.$domain,
                'status' => 'inactive',
                'subscription_plan' => 'basic',
                'max_members' => 150,
                'sso_config' => json_encode(['state' => $state, 'category' => 'college_of_education']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks for efficiency
        foreach (array_chunk($institutions, 50) as $chunk) {
            DB::table('organizations')->insert($chunk);
        }

        $total = count($institutions);
        $uniCount = count($federalUniversities) + count($stateUniversities) + count($privateUniversities);
        $polyCount = count($federalPolytechnics) + count($statePolytechnics);
        $coeCount = count($collegesOfEducation);

        $this->command->info("Nigerian institutions seeded: {$uniCount} universities, {$polyCount} polytechnics, {$coeCount} colleges of education ({$total} total).");
    }
}
