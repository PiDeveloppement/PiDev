package com.melocode.pigestion.model;

public class Question {
    private int idQuestion;
    private int idEvent;
    private String texteQuestion;
    private String bonneReponse;
    private int points;

    // Constructeur complet
    public Question(int idQuestion, int idEvent, String texteQuestion, String bonneReponse, int points) {
        this.idQuestion = idQuestion;
        this.idEvent = idEvent;
        this.texteQuestion = texteQuestion;
        this.bonneReponse = bonneReponse;
        this.points = points;
    }

    // --- GETTERS (Indispensables pour QuestionService et les contrôleurs) ---

    public int getIdQuestion() {
        return idQuestion;
    }

    public int getIdEvent() {
        return idEvent;
    }

    public String getTexteQuestion() {
        return texteQuestion;
    }

    public String getBonneReponse() {
        return bonneReponse;
    }

    public int getPoints() {
        return points;
    }

    // --- SETTERS (Indispensables pour la modification côté organisateur) ---

    public void setIdQuestion(int idQuestion) {
        this.idQuestion = idQuestion;
    }

    public void setIdEvent(int idEvent) {
        this.idEvent = idEvent;
    }

    public void setTexteQuestion(String texteQuestion) {
        this.texteQuestion = texteQuestion;
    }

    public void setBonneReponse(String bonneReponse) {
        this.bonneReponse = bonneReponse;
    }

    public void setPoints(int points) {
        this.points = points;
    }
}