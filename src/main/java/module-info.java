module com.example.pidev {
    requires javafx.controls;
    requires java.sql;
    requires javafx.fxml;
    requires atlantafx.base;
    requires jfxtras.controls;
    requires jfxtras.common;

    // Exportez tous les packages nécessaires
    exports com.example.pidev;
    exports com.example.pidev.model.event;
    exports com.example.pidev.model.sponsor;
    exports com.example.pidev.controller.event;
    exports com.example.pidev.utils;

    // Ouvrez tous les packages à javafx.fxml
    opens com.example.pidev to javafx.fxml;
    opens com.example.pidev.controller.dashboard to javafx.fxml;
    opens com.example.pidev.controller.event to javafx.fxml;
    opens com.example.pidev.controller.sponsor to javafx.fxml;
    opens com.example.pidev.utils to javafx.fxml;
}