const axios = require('axios')
const express = require('express');
const udon = require('./api/udon');
const app = express();

app.use("/api/udon", udon);
// PORT  Set in windows with set PORT = #
const port = process.env.PORT || 9001;
app.listen(port, () => console.log(`Listening on port ${port}...`));