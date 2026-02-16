module com.melocode.pigestion {
    requires javafx.controls;
    requires javafx.fxml;
    requires java.sql;
    requires mysql.connector.j;
    requires java.net.http;


    // AJOUTE CETTE LIGNE POUR REGLER L'ERREUR PDF
    requires itextpdf;

    opens com.melocode.pigestion.controller to javafx.fxml;
    opens com.melocode.pigestion.model to javafx.base;
    // Permet à iText ou d'autres services d'accéder à tes classes si besoin
    opens com.melocode.pigestion.service to javafx.fxml;

    exports com.melocode.pigestion;
    exports com.melocode.pigestion.controller;
    exports com.melocode.pigestion.model;
    exports com.melocode.pigestion.service;
    exports com.melocode.pigestion.utils;
}