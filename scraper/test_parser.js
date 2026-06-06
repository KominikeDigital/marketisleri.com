const months = {
    'ocak': 1, 'şubat': 2, 'mart': 3, 'nisan': 4, 'mayıs': 5, 'haziran': 6,
    'temmuz': 7, 'ağustos': 8, 'eylül': 9, 'ekim': 10, 'kasım': 11, 'aralık': 12
};

function parseTurkishDateRange(titleText) {
    const now = new Date();
    let year = now.getFullYear();
    const currentMonth = now.getMonth() + 1; // 1-indexed

    const text = titleText.toLowerCase().trim().replace(/\s+/g, ' ');
    
    // Helper to format date
    const formatDate = (y, m, d) => {
        const mStr = String(m).padStart(2, '0');
        const dStr = String(d).padStart(2, '0');
        return `${y}-${mStr}-${dStr}`;
    };

    // Helper to add days
    const addDays = (dateStr, days) => {
        const d = new Date(dateStr);
        d.setDate(d.getDate() + days);
        return d.toISOString().split('T')[0];
    };

    // Regex 1: Range across months (e.g., "24 Mart-31 Aralık" or "03 Haziran-24 Ağustos" or "30 Mayıs - 5 Haziran")
    const rangeMonthsRegex = /(\d+)\s*([a-zşğçıöü]+)\s*-\s*(\d+)\s*([a-zşğçıöü]+)/;
    let match = text.match(rangeMonthsRegex);
    if (match) {
        const startDay = parseInt(match[1], 10);
        const startMonthName = match[2];
        const endDay = parseInt(match[3], 10);
        const endMonthName = match[4];
        
        const startMonth = months[startMonthName];
        const endMonth = months[endMonthName];
        
        if (startMonth && endMonth) {
            let startYear = year;
            let endYear = year;
            
            // Year rollover detection (e.g. Dec to Jan)
            if (startMonth === 12 && endMonth === 1) {
                endYear = startYear + 1;
            }
            
            return {
                startDate: formatDate(startYear, startMonth, startDay),
                endDate: formatDate(endYear, endMonth, endDay)
            };
        }
    }

    // Regex 2: Range within same month (e.g., "03-29 Haziran" or "05-08 Haziran" or "25-31 Mayıs")
    const rangeSameMonthRegex = /(\d+)\s*-\s*(\d+)\s+([a-zşğçıöü]+)/;
    match = text.match(rangeSameMonthRegex);
    if (match) {
        const startDay = parseInt(match[1], 10);
        const endDay = parseInt(match[2], 10);
        const monthName = match[3];
        const month = months[monthName];
        
        if (month) {
            return {
                startDate: formatDate(year, month, startDay),
                endDate: formatDate(year, month, endDay)
            };
        }
    }

    // Regex 3: Single date with 'dan itibaren' or similar suffix (e.g. "4 Haziran'dan itibaren" or "26 Mayıs'tan itibaren")
    const relativeDateRegex = /(\d+)\s*([a-zşğçıöü]+)(?:'?[a-zşğçıöü]+)?\s*dan\s+itibaren|(\d+)\s*([a-zşğçıöü]+)(?:'?[a-zşğçıöü]+)?\s*tan\s+itibaren/i;
    match = text.match(relativeDateRegex);
    if (match) {
        const day = parseInt(match[1] || match[3], 10);
        const monthName = match[2] || match[4];
        const month = months[monthName];
        if (month) {
            const startDate = formatDate(year, month, day);
            const endDate = addDays(startDate, 7);
            return { startDate, endDate };
        }
    }

    // Regex 4: Single Date (e.g., "25 Mayıs Pazartesi" or "03 Haziran Çarşamba")
    const singleDateRegex = /(\d+)\s+([a-zşğçıöü]+)/;
    match = text.match(singleDateRegex);
    if (match) {
        const day = parseInt(match[1], 10);
        const monthName = match[2];
        const month = months[monthName];
        
        if (month) {
            // Roll year detection
            let brochureYear = year;
            if (currentMonth === 12 && month === 1) {
                brochureYear += 1;
            } else if (currentMonth === 1 && month === 12) {
                brochureYear -= 1;
            }
            
            const startDate = formatDate(brochureYear, month, day);
            const endDate = addDays(startDate, 7);
            return { startDate, endDate };
        }
    }

    // Default Fallback
    const startDate = formatDate(year, currentMonth, now.getDate());
    const endDate = addDays(startDate, 7);
    return { startDate, endDate };
}

// Test cases
const testCases = [
    "25 Mayıs Pazartesi",
    "24 Mart-31 Aralık",
    "BAYRAM",
    "26 Mayıs Salı",
    "03-29 Haziran",
    "03 Haziran Çarşamba",
    "03 Haziran-24 Ağustos",
    "02 Haziran Salı",
    "05 Haziran Cuma",
    "05-08 Haziran",
    "10 Haziran Çarşamba",
    "Meyve-Sebze",
    "06-12 Haziran",
    "30 Mayıs - 5 Haziran",
    "25-31 Mayıs",
    "4 Haziran'dan itibaren",
    "26 Mayıs'tan itibaren"
];

testCases.forEach(tc => {
    const parsed = parseTurkishDateRange(tc);
    console.log(`Title: "${tc}" => Start: ${parsed.startDate}, End: ${parsed.endDate}`);
});
