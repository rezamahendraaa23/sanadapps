<?php

namespace App\Modules\Masjid\Support;

/**
 * Templat ayat dan hadis yang sering dipakai di pesantren. Masuk pustaka sebagai belum terverifikasi,
 * jadi ustadz atau takmir tetap mengecek teks Arab, terjemah, dan rujukan sebelum tayang.
 */
final class QuoteTemplates
{
    public const TOPICS = [
        'jamaah' => 'Sholat berjamaah',
        'jumat' => 'Hari Jumat',
        'ilmu' => 'Ilmu dan belajar',
        'quran' => 'Al-Quran',
        'adab' => 'Adab dan akhlak',
        'bersih' => 'Kebersihan',
        'sabar' => 'Sabar dan semangat',
        'orangtua' => 'Orang tua',
    ];

    /**
     * @return list<array{ar: string, id: string, ref: string}>
     */
    public static function for(string $topic): array
    {
        return self::ALL[$topic] ?? [];
    }

    private const ALL = [
        'jamaah' => [
            ['ar' => 'وَأَقِيمُوا الصَّلَاةَ وَآتُوا الزَّكَاةَ وَارْكَعُوا مَعَ الرَّاكِعِينَ', 'id' => 'Dan dirikanlah sholat, tunaikanlah zakat, dan rukuklah bersama orang-orang yang rukuk.', 'ref' => 'QS. Al-Baqarah: 43'],
            ['ar' => 'صَلَاةُ الْجَمَاعَةِ أَفْضَلُ مِنْ صَلَاةِ الْفَذِّ بِسَبْعٍ وَعِشْرِينَ دَرَجَةً', 'id' => 'Sholat berjamaah lebih utama daripada sholat sendirian dengan dua puluh tujuh derajat.', 'ref' => 'HR. Bukhari no. 645 dan Muslim no. 650'],
            ['ar' => 'سَوُّوا صُفُوفَكُمْ، فَإِنَّ تَسْوِيَةَ الصُّفُوفِ مِنْ إِقَامَةِ الصَّلَاةِ', 'id' => 'Luruskanlah saf kalian, karena meluruskan saf termasuk bagian dari menegakkan sholat.', 'ref' => 'HR. Bukhari no. 723'],
            ['ar' => 'صَلُّوا كَمَا رَأَيْتُمُونِي أُصَلِّي', 'id' => 'Sholatlah kalian sebagaimana kalian melihat aku sholat.', 'ref' => 'HR. Bukhari no. 631'],
            ['ar' => 'قَدْ أَفْلَحَ الْمُؤْمِنُونَ الَّذِينَ هُمْ فِي صَلَاتِهِمْ خَاشِعُونَ', 'id' => 'Sungguh beruntung orang-orang yang beriman, yaitu orang yang khusyuk dalam sholatnya.', 'ref' => 'QS. Al-Mu\'minun: 1-2'],
            ['ar' => 'إِنَّ الصَّلَاةَ تَنْهَىٰ عَنِ الْفَحْشَاءِ وَالْمُنْكَرِ', 'id' => 'Sesungguhnya sholat itu mencegah dari perbuatan keji dan mungkar.', 'ref' => 'QS. Al-\'Ankabut: 45'],
        ],
        'jumat' => [
            ['ar' => 'يَا أَيُّهَا الَّذِينَ آمَنُوا إِذَا نُودِيَ لِلصَّلَاةِ مِنْ يَوْمِ الْجُمُعَةِ فَاسْعَوْا إِلَىٰ ذِكْرِ اللَّهِ وَذَرُوا الْبَيْعَ', 'id' => 'Wahai orang-orang yang beriman, apabila diseru untuk sholat pada hari Jumat, bersegeralah mengingat Allah dan tinggalkanlah jual beli.', 'ref' => 'QS. Al-Jumu\'ah: 9'],
            ['ar' => 'خَيْرُ يَوْمٍ طَلَعَتْ عَلَيْهِ الشَّمْسُ يَوْمُ الْجُمُعَةِ', 'id' => 'Sebaik-baik hari yang matahari terbit padanya adalah hari Jumat.', 'ref' => 'HR. Muslim no. 854'],
            ['ar' => 'إِذَا قُلْتَ لِصَاحِبِكَ يَوْمَ الْجُمُعَةِ أَنْصِتْ، وَالْإِمَامُ يَخْطُبُ، فَقَدْ لَغَوْتَ', 'id' => 'Jika engkau berkata kepada temanmu "Diamlah" pada hari Jumat ketika imam sedang berkhutbah, sungguh engkau telah berbuat sia-sia.', 'ref' => 'HR. Bukhari no. 934 dan Muslim no. 851'],
        ],
        'ilmu' => [
            ['ar' => 'طَلَبُ الْعِلْمِ فَرِيضَةٌ عَلَىٰ كُلِّ مُسْلِمٍ', 'id' => 'Menuntut ilmu itu wajib bagi setiap muslim.', 'ref' => 'HR. Ibnu Majah no. 224'],
            ['ar' => 'مَنْ سَلَكَ طَرِيقًا يَلْتَمِسُ فِيهِ عِلْمًا سَهَّلَ اللَّهُ لَهُ بِهِ طَرِيقًا إِلَى الْجَنَّةِ', 'id' => 'Barang siapa menempuh jalan untuk mencari ilmu, Allah mudahkan baginya jalan menuju surga.', 'ref' => 'HR. Muslim no. 2699'],
            ['ar' => 'يَرْفَعِ اللَّهُ الَّذِينَ آمَنُوا مِنْكُمْ وَالَّذِينَ أُوتُوا الْعِلْمَ دَرَجَاتٍ', 'id' => 'Allah akan mengangkat orang-orang yang beriman di antara kalian dan orang-orang yang diberi ilmu beberapa derajat.', 'ref' => 'QS. Al-Mujadilah: 11'],
            ['ar' => 'وَقُلْ رَبِّ زِدْنِي عِلْمًا', 'id' => 'Dan katakanlah: Ya Tuhanku, tambahkanlah kepadaku ilmu.', 'ref' => 'QS. Taha: 114'],
            ['ar' => 'اقْرَأْ بِاسْمِ رَبِّكَ الَّذِي خَلَقَ', 'id' => 'Bacalah dengan menyebut nama Tuhanmu yang menciptakan.', 'ref' => 'QS. Al-\'Alaq: 1'],
        ],
        'quran' => [
            ['ar' => 'خَيْرُكُمْ مَنْ تَعَلَّمَ الْقُرْآنَ وَعَلَّمَهُ', 'id' => 'Sebaik-baik kalian adalah orang yang belajar Al-Quran dan mengajarkannya.', 'ref' => 'HR. Bukhari no. 5027'],
            ['ar' => 'اقْرَءُوا الْقُرْآنَ فَإِنَّهُ يَأْتِي يَوْمَ الْقِيَامَةِ شَفِيعًا لِأَصْحَابِهِ', 'id' => 'Bacalah Al-Quran, karena ia akan datang pada hari kiamat sebagai pemberi syafaat bagi pembacanya.', 'ref' => 'HR. Muslim no. 804'],
            ['ar' => 'وَلَقَدْ يَسَّرْنَا الْقُرْآنَ لِلذِّكْرِ فَهَلْ مِنْ مُدَّكِرٍ', 'id' => 'Dan sungguh telah Kami mudahkan Al-Quran untuk peringatan, maka adakah orang yang mau mengambil pelajaran?', 'ref' => 'QS. Al-Qamar: 17'],
        ],
        'adab' => [
            ['ar' => 'إِنَّمَا الْأَعْمَالُ بِالنِّيَّاتِ', 'id' => 'Sesungguhnya setiap amal itu tergantung pada niatnya.', 'ref' => 'HR. Bukhari no. 1 dan Muslim no. 1907'],
            ['ar' => 'مَنْ كَانَ يُؤْمِنُ بِاللَّهِ وَالْيَوْمِ الْآخِرِ فَلْيَقُلْ خَيْرًا أَوْ لِيَصْمُتْ', 'id' => 'Barang siapa beriman kepada Allah dan hari akhir, hendaklah ia berkata baik atau diam.', 'ref' => 'HR. Bukhari no. 6018 dan Muslim no. 47'],
            ['ar' => 'لَا يُؤْمِنُ أَحَدُكُمْ حَتَّىٰ يُحِبَّ لِأَخِيهِ مَا يُحِبُّ لِنَفْسِهِ', 'id' => 'Tidak sempurna iman salah seorang dari kalian hingga ia mencintai untuk saudaranya apa yang ia cintai untuk dirinya sendiri.', 'ref' => 'HR. Bukhari no. 13 dan Muslim no. 45'],
            ['ar' => 'تَبَسُّمُكَ فِي وَجْهِ أَخِيكَ لَكَ صَدَقَةٌ', 'id' => 'Senyummu di hadapan saudaramu adalah sedekah bagimu.', 'ref' => 'HR. Tirmidzi no. 1956'],
            ['ar' => 'وَإِنَّكَ لَعَلَىٰ خُلُقٍ عَظِيمٍ', 'id' => 'Dan sesungguhnya engkau benar-benar berbudi pekerti yang agung.', 'ref' => 'QS. Al-Qalam: 4'],
        ],
        'bersih' => [
            ['ar' => 'الطُّهُورُ شَطْرُ الْإِيمَانِ', 'id' => 'Bersuci itu sebagian dari iman.', 'ref' => 'HR. Muslim no. 223'],
            ['ar' => 'إِنَّ اللَّهَ يُحِبُّ التَّوَّابِينَ وَيُحِبُّ الْمُتَطَهِّرِينَ', 'id' => 'Sesungguhnya Allah menyukai orang-orang yang bertobat dan menyukai orang-orang yang menyucikan diri.', 'ref' => 'QS. Al-Baqarah: 222'],
        ],
        'sabar' => [
            ['ar' => 'يَا أَيُّهَا الَّذِينَ آمَنُوا اسْتَعِينُوا بِالصَّبْرِ وَالصَّلَاةِ ۚ إِنَّ اللَّهَ مَعَ الصَّابِرِينَ', 'id' => 'Wahai orang-orang yang beriman, mohonlah pertolongan dengan sabar dan sholat. Sesungguhnya Allah bersama orang-orang yang sabar.', 'ref' => 'QS. Al-Baqarah: 153'],
            ['ar' => 'فَإِنَّ مَعَ الْعُسْرِ يُسْرًا إِنَّ مَعَ الْعُسْرِ يُسْرًا', 'id' => 'Maka sesungguhnya bersama kesulitan ada kemudahan. Sesungguhnya bersama kesulitan ada kemudahan.', 'ref' => 'QS. Al-Insyirah: 5-6'],
            ['ar' => 'أَلَا بِذِكْرِ اللَّهِ تَطْمَئِنُّ الْقُلُوبُ', 'id' => 'Ingatlah, hanya dengan mengingat Allah hati menjadi tenteram.', 'ref' => 'QS. Ar-Ra\'d: 28'],
            ['ar' => 'وَالْعَصْرِ إِنَّ الْإِنْسَانَ لَفِي خُسْرٍ إِلَّا الَّذِينَ آمَنُوا وَعَمِلُوا الصَّالِحَاتِ وَتَوَاصَوْا بِالْحَقِّ وَتَوَاصَوْا بِالصَّبْرِ', 'id' => 'Demi masa. Sungguh manusia berada dalam kerugian, kecuali orang-orang yang beriman, beramal saleh, saling menasihati dalam kebenaran, dan saling menasihati dalam kesabaran.', 'ref' => 'QS. Al-\'Asr: 1-3'],
        ],
        'orangtua' => [
            ['ar' => 'وَقُلْ رَبِّ ارْحَمْهُمَا كَمَا رَبَّيَانِي صَغِيرًا', 'id' => 'Dan ucapkanlah: Wahai Tuhanku, sayangilah keduanya sebagaimana mereka berdua telah mendidikku sewaktu kecil.', 'ref' => 'QS. Al-Isra: 24'],
            ['ar' => 'أَنِ اشْكُرْ لِي وَلِوَالِدَيْكَ إِلَيَّ الْمَصِيرُ', 'id' => 'Bersyukurlah kepada-Ku dan kepada kedua orang tuamu. Hanya kepada-Ku tempat kembalimu.', 'ref' => 'QS. Luqman: 14'],
        ],
    ];
}
