var express = require('express');
var app = express();
var mongoose = require('mongoose');

//Database
mongoose.set('strictQuery', false);
mongoose.connect('mongodb://mongodb/A5', {
        useNewUrlParser: true,
        useUnifiedTopology: true
    })
    .then(() => {
        console.log("Connected to MongoDB");
    })
    .catch(err => {
        console.log("MongoDB connection error: " + err);
    });

// Define schema for weather data
var weatherSchema = new mongoose.Schema({
    date: String,
    meanT: Number,
    maxT: Number,
    minT: Number,
    humidity: Number,
    rain: Number
});

// Create a model based on the schema
var Weather = mongoose.model('wrecords', weatherSchema);

app.use(express.json());
app.use(express.urlencoded({
    extended: false
}));

function DateValidation(day, month, year) {
    const max = 2033;
    const min = 2013;

    if (isNaN(day) || isNaN(month) || isNaN(year)) {
        return false;
    }
    if (year < min || year > max) {
        return false;
    }
    if (month < 1 || month > 12) {
        return false;
    }
    if (day < 1 || day > 31) {
        return false;
    }

    if (month === 2) {
        if ((year % 4 === 0 && year % 100 !== 0) || year % 400 === 0) {
            return day <= 29;
        } else {
            return day <= 28;
        }
    }

    if (month === 4 || month === 6 || month === 9 || month === 11) {
        return day <= 30;
    }

    return true;
}

// TASK B
app.post('/weather/:year/:month/:day', async function(req, res) {
    var year = parseInt(req.params.year);
    var month = parseInt(req.params.month);
    var day = parseInt(req.params.day);

    if (!DateValidation(day, month, year)) {
        res.status(400).json({
            'error': 'not a valid year/month/date'
        });
        return;
    }

    var datefull = `${year}${('0' + (month)).slice(-2)}${('0' + day).slice(-2)}`;
    try {
        const existingRecord = await Weather.findOne({
            date: datefull
        });

        if (existingRecord) {
            res.status(403).json({
                'error': 'find an existing record. Cannot override!!'
            });
            return;
        }

        const newRecord = new Weather({
            date: datefull,
            meanT: req.body.meanT,
            maxT: req.body.maxT,
            minT: req.body.minT,
            humidity: req.body.humidity,
            rain: req.body.rain
        });

        await newRecord.save();

        res.status(200).json({
            'okay': 'record added'
        });
    } catch (err) {
        res.status(500).json({
            'error': 'system error'
        });
    }
});

// TASK C
app.get('/weather/:year/:month/:day', async function(req, res, next) {
    const year = parseInt(req.params.year);

    if (isNaN(year)) {
        return next();
    }

    const month = parseInt(req.params.month);
    const day = parseInt(req.params.day);

    if (!DateValidation(day, month, year)) {
        return res.status(400).json({
            'error': 'not a valid year/month/date'
        });
    }

    var datefull = `${year}${('0' + (month)).slice(-2)}${('0' + day).slice(-2)}`;
    try {
        const record = await Weather.findOne({
            date: datefull
        });

        if (!record) {
            return res.status(404).json({
                'error': 'not found'
            });
        }

        const weatherData = {
            Year: year,
            Month: month,
            Date: day,
            "Avg Temp": record.meanT,
            "Max Temp": record.maxT,
            "Min Temp": record.minT,
            Humidity: record.humidity,
            Rainfall: record.rain
        };

        return res.status(200).json(weatherData);
    } catch (err) {
        return res.status(500).json({
            'error': 'system error'
        });
    }
});


//TASK D
app.get('/weather/:type/:year/:month', async function(req, res, next) {
    const type = req.params.type;

    if (type !== "temp" && type !== "humi" && type !== "rain") {
        return next();
    }

    const year = parseInt(req.params.year);
    const month = parseInt(req.params.month);
    const day = 1;

    if (!DateValidation(day, month, year)) {
        return res.status(400).json({
            'error': 'not a valid year/month'
        });
    }

    var datestart = `${year}${('0' + month).slice(-2)}01`;
    var dateend = `${year}${('0' + month).slice(-2)}31`;

    try {
        const records = await Weather.find({
            date: {
                $gte: datestart,
                $lte: dateend
            }
        });

        if (records.length === 0) {
            return res.status(404).json({
                'error': 'not found'
            });
        }

        const complete_info = {
            'temp': {
                avg: 'meanT',
                max: 'maxT',
                min: 'minT'
            },
            'humi': {
                avg: 'humidity',
                max: 'humidity',
                min: 'humidity'
            },
            'rain': {
                avg: 'rain',
                max: 'rain',
                min: null
            }
        };

        const information = complete_info[type];
        if (!information) {
            return res.status(400).json({
                'error': 'invalid request'
            });
        }

        var temporarySum = 0;
        var temporaryMax = records[0][information.max];
        var temporaryMin = records[0][information.min];
        var amount = 0;

        for (let i = 0; i < records.length; i++) {
            const record = records[i];
            if (record[information.avg]) {
                temporarySum += record[information.avg];
                amount += 1;
            }
            if (record[information.max] > temporaryMax) {
                temporaryMax = record[information.max];
            }
            if (record[information.min] < temporaryMin) {
                temporaryMin = record[information.min];
            }
        }

        const avg_final = type === 'temp' ? 'Temperature' : type === 'humi' ? 'Humidity' : 'Rainfall';
        const max_final = type === 'temp' ? 'Temperature' : type === 'humi' ? 'Humidity' : 'Daily Rainfall';
        const min_final = type === 'humi' ? 'Humidity' : 'Temperature';

        var output = {}; output.Year = year; output.Month = month;

        if (amount > 0) {
            output[`Avg ${avg_final}`] = (temporarySum / amount);
        } else {
            output[`Avg ${avg_final}`] = 0;
        }

        if (type === 'rain') {
            output['Max Daily Rainfall'] = temporaryMax;
        } else {
            output[`Max ${max_final}`] = temporaryMax;
            output[`Min ${min_final}`] = temporaryMin;
        }

        return res.status(200).json(output);
    } catch (err) {
        return res.status(500).json({
            'error': err.message
        });
    }
});



// TASK E
app.all('*', function(req, res) {
    res.status(400).json({
        'error': `Cannot ${req.method} ${req.url}`
    });
});


// error handler
app.use(function(err, req, res, next) {
    res.status(err.status || 500);
    res.json({
        'error': err.message
    });
});

app.listen(8000, () => {
    console.log('Weather app listening on port 8000!')
});