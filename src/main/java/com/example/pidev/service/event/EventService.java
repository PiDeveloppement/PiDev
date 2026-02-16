package com.example.pidev.service.event;

import com.example.pidev.model.event.Event;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

public class EventService {

    public ObservableList<Event> getAllEvents() {
        ObservableList<Event> events = FXCollections.observableArrayList();

        // Données de test
        events.add(new Event(1001, "Forum des Métiers Informatiques", "15 Mars 2024",
                "Professionnel", "En cours", 245, 5000.0));
        events.add(new Event(1002, "Conférence IA & Machine Learning", "22 Mars 2024",
                "Académique", "Planifié", 180, 3500.0));
        events.add(new Event(1003, "Tournoi de Football Universitaire", "10 Avril 2024",
                "Sportif", "Ouvert", 85, 1200.0));
        events.add(new Event(1004, "Festival des Arts Universitaires", "5 Mai 2024",
                "Culturel", "Planifié", 45, 2800.0));
        events.add(new Event(1005, "Hackathon Innovation 2024", "18 Mars 2024",
                "Académique", "Terminé", 120, 4200.0));
        events.add(new Event(1006, "Semaine de l'Entrepreneuriat", "30 Avril 2024",
                "Professionnel", "Ouvert", 210, 6500.0));
        events.add(new Event(1007, "Concours de Débat Universitaire", "12 Mars 2024",
                "Culturel", "Annulé", 0, 800.0));
        events.add(new Event(1008, "Conférence Big Data Analytics", "25 Avril 2024",
                "Académique", "En cours", 150, 2500.0));
        events.add(new Event(1009, "Tournoi de Basketball Universitaire", "8 Mai 2024",
                "Sportif", "Planifié", 40, 800.0));
        events.add(new Event(1010, "Salon du Recrutement IT", "20 Mars 2024",
                "Professionnel", "Terminé", 300, 8000.0));

        return events;
    }
}

