const express = require("express");
const app = express();
const port = 3000;

app.get("/", (req, res) => {
    res.send("🚀 Transporte App - Despliegue Automatizado Exitoso!");
});

app.get("/health", (req, res) => {
    res.status(200).send("OK");
});

app.listen(port, () => {
    console.log(`✅ App corriendo en http://localhost:${port}`);
});
