module com.example.pidev {
    requires javafx.controls;
    requires javafx.fxml;
    requires javafx.web;
    requires java.sql;
    requires atlantafx.base;
    requires mysql.connector.j;

    // Noms de modules pour iText 7
    requires kernel;
    requires layout;
    requires io;
    requires javafx.graphics;

    // 1. Autoriser JavaFX à lire les fichiers FXML dans ce package
    opens com.example.pidev to javafx.fxml;

    // 2. Autoriser JavaFX à injecter les @FXML dans vos contrôleurs
    opens com.example.pidev.controller.resource to javafx.fxml;

    // 3. Autoriser la TableView à lire les propriétés de vos modèles
    opens com.example.pidev.model.resource to javafx.base;

    // 4. Exposer vos packages pour qu'ils soient accessibles
    exports com.example.pidev;
    exports com.example.pidev.controller.resource;
    exports com.example.pidev.model.resource;
}