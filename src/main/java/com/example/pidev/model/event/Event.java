package com.example.pidev.model.event;

import javafx.beans.property.*;

public class Event {
    private final IntegerProperty id = new SimpleIntegerProperty();
    private final StringProperty title = new SimpleStringProperty();
    private final StringProperty date = new SimpleStringProperty();
    private final StringProperty category = new SimpleStringProperty();
    private final StringProperty status = new SimpleStringProperty();
    private final IntegerProperty participants = new SimpleIntegerProperty();
    private final DoubleProperty budget = new SimpleDoubleProperty();

    // Constructeurs
    public Event() {}

    public Event(int id, String title, String date, String category,
                 String status, int participants, double budget) {
        setId(id);
        setTitle(title);
        setDate(date);
        setCategory(category);
        setStatus(status);
        setParticipants(participants);
        setBudget(budget);
    }

    // Getters et Setters
    public int getId() { return id.get(); }
    public void setId(int value) { id.set(value); }
    public IntegerProperty idProperty() { return id; }

    public String getTitle() { return title.get(); }
    public void setTitle(String value) { title.set(value); }
    public StringProperty titleProperty() { return title; }

    public String getDate() { return date.get(); }
    public void setDate(String value) { date.set(value); }
    public StringProperty dateProperty() { return date; }

    public String getCategory() { return category.get(); }
    public void setCategory(String value) { category.set(value); }
    public StringProperty categoryProperty() { return category; }

    public String getStatus() { return status.get(); }
    public void setStatus(String value) { status.set(value); }
    public StringProperty statusProperty() { return status; }

    public int getParticipants() { return participants.get(); }
    public void setParticipants(int value) { participants.set(value); }
    public IntegerProperty participantsProperty() { return participants; }

    public double getBudget() { return budget.get(); }
    public void setBudget(double value) { budget.set(value); }
    public DoubleProperty budgetProperty() { return budget; }
}

