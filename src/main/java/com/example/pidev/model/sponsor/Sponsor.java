package com.example.pidev.model.sponsor;

public class Sponsor {
    private int id;
    private int event_id;
    private String company_name;
    private String logo_url;
    private double contribution_amt;
    private String contact_email;

    public Sponsor() {}

    public Sponsor(int id, int event_id, String company_name, String logo_url,
                   double contribution_amt, String contact_email) {
        this.id = id;
        this.event_id = event_id;
        this.company_name = company_name;
        this.logo_url = logo_url;
        this.contribution_amt = contribution_amt;
        this.contact_email = contact_email;
    }

    public Sponsor(int event_id, String company_name, String logo_url,
                   double contribution_amt, String contact_email) {
        this.event_id = event_id;
        this.company_name = company_name;
        this.logo_url = logo_url;
        this.contribution_amt = contribution_amt;
        this.contact_email = contact_email;
    }

    // Getters
    public int getId() { return id; }
    public int getEvent_id() { return event_id; }
    public String getCompany_name() { return company_name; }
    public String getLogo_url() { return logo_url; }
    public double getContribution_amt() { return contribution_amt; }
    public String getContact_email() { return contact_email; }

    // Setters
    public void setId(int id) { this.id = id; }
    public void setEvent_id(int event_id) { this.event_id = event_id; }
    public void setCompany_name(String company_name) { this.company_name = company_name; }
    public void setLogo_url(String logo_url) { this.logo_url = logo_url; }
    public void setContribution_amt(double contribution_amt) { this.contribution_amt = contribution_amt; }
    public void setContact_email(String contact_email) { this.contact_email = contact_email; }
}