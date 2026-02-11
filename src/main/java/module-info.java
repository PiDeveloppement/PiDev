module com.example.pidev {
    requires javafx.controls;
    requires javafx.fxml;
    requires javafx.web;
    requires java.sql;
    requires atlantafx.base;
    requires mysql.connector.j;

    // Noms de modules officiels pour iText 7
    requires kernel;
    requires layout;
    requires io;

    // Si "kernel" seul ne fonctionne pas, utilisez le nom complet :
    // requires com.itextpdf.kernel;
    // requires com.itextpdf.layout;
    // requires com.itextpdf.io;

    opens com.example.pidev.controller.resource to javafx.fxml;
    opens com.example.pidev.model.resource to javafx.base;

    exports com.example.pidev;
}