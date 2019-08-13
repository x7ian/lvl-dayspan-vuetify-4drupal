<template>
  <v-app id="dayspan" v-cloak>

    <ds-calendar-app ref="app"
      :calendar="calendar"
      :read-only="readOnly"
      @change="saveState">

      <template slot="title">
        DaySpan
      </template>

      <template slot="menuRight">
      </template>

      <template slot="eventPopover" slot-scope="slotData">
        <ds-calendar-event-popover
          v-bind="slotData"
          :read-only="readOnly"
          @finish="saveState"
        >

          <template slot="eventPopoverToolbarActions" slot-scope="slotData">
            &nbsp;
          </template>

          <template slot="eventPopoverToolbarRight" slot-scope="slotData">
            <v-btn dark color="pink"
              class="ds-create-popover-cancel"
              @click="cancelEvent(slotData)">

                <v-icon  left>remove_circle</v-icon>
                <span>Cancel</span>

              </v-btn>
          </template>

        </ds-calendar-event-popover>
      </template>

      <template slot="eventCreatePopover" slot-scope="{placeholder, calendar, close}">
        <ds-calendar-event-create-popover
          :calendar-event="placeholder"
          :calendar="calendar"
          :close="clearCreatePopover"
          @create-edit="$refs.app.editPlaceholder"
          @create-popover-closed="saveState"
          @creating="creating"
        >

          <template slot="eventCreatePopoverTitle" slot-scope="{startDate, occurs}">
            &nbsp;
          </template>

          <template slot="eventCreatePopoverBodyBottom" slot-scope="slotData">
            <v-list expand three-line>
              <v-list-tile>
                <v-list-tile-avatar>
                  <v-icon>alarm</v-icon>
                </v-list-tile-avatar>
                <v-list-tile-content>
                  <v-subheader>Duration in hours</v-subheader>
                  <v-text-field
                    hide-details solo flat
                    type="number"
                    v-model.number="defaultDuration"
                    :disabled="false"
                  ></v-text-field>

                </v-list-tile-content>
              </v-list-tile>

              <v-list-tile>
                <v-list-tile-avatar>
                  <v-icon>location_on</v-icon>
                </v-list-tile-avatar>
                <v-list-tile-content>
                  <v-subheader class="pa-0">Select a patient to create a session</v-subheader>
                  <v-autocomplete persistent-hint
                    v-model="selectedPatient"
                    :items="patients"
                    :label="'Patient'"
                    @change="patientSelected(slotData)"
                  ></v-autocomplete>
                </v-list-tile-content>
              </v-list-tile>

              <v-list-tile>
                <v-list-tile-avatar>
                  <v-icon>subject</v-icon>
                </v-list-tile-avatar>
                <v-list-tile-content>

                    <v-textarea
                      hide-details single-line solo flat full-width
                      :label="labels.description"
                      v-model="defaultDescription"
                    ></v-textarea>

                </v-list-tile-content>
              </v-list-tile>
            </v-list>
          </template>

        </ds-calendar-event-create-popover>
      </template>

      <template slot="eventTimeTitle" slot-scope="{calendarEvent, details}">
        <div>
          <v-icon class="ds-ev-icon"
            v-if="details.icon"
            size="14"
            :style="{color: details.forecolor}">
            {{ details.icon }}
          </v-icon>
          <strong class="ds-ev-title">{{ details.title }}</strong>
        </div>
        <div class="ds-ev-description">{{ getCalendarTime( calendarEvent ) }}</div>
      </template>

      <template slot="drawerBottom">
        <v-container fluid>
          <v-layout wrap align-center>
            <v-flex xs12>
              <v-checkbox box
                label="Read Only?"
                v-model="readOnly"
              ></v-checkbox>
            </v-flex>
            <v-flex xs12>
              <v-select
                label="Language"
                :items="locales"
                v-model="currentLocale"
                @input="setLocale"
              ></v-select>
            </v-flex>
          </v-layout>
        </v-container>
      </template>

    </ds-calendar-app>

  </v-app>
</template>

<script>
import Vue from 'vue';
import axios from "axios";
import { dsMerge } from './functions';
import { Calendar, Weekday, Month, Sorts } from 'dayspan';
import * as moment from 'moment';
import { drupalConnect } from './drupalConnect';

export default {

  name: 'dayspan',

  data: vm => ({
    storeKey: 'dayspanState',
    calendar: Calendar.months(),
    readOnly: false,
    currentLocale: vm.$dayspan.currentLocale,
    locales: [
      { value: 'en', text: 'English' },
      { value: 'es', text: 'Spanish' },
    ],
    eventTypes: {
      hours: {
        color: 'Blue'
      },
      sessions: {
        color: 'Deep Purple',
        createLink: '/node/add/sessions',
        //typePath: '/rest/type/node/sessions',
      },
      holidays: {
        color: 'Red'
      },
    },
    defaultEvents: [],
    defaultDuration: 1,
    patients: [],
    selectedPatient: 0,
    defaultDescription: '',
    defaultType: 'hours',
    labels: {
      updateSession: 'Save',
      cancelSession: 'Cancel Session',
      enableHour: 'Enable Hour',
      createSession: 'Create Session',
      description: 'Message',
    },
  }),

  mounted()
  {
    window.app = this.$refs.app;
    this.dConnect = new drupalConnect();
    this.dConnect.init();

    this.$dayspan.supports.color = false;
    this.$dayspan.supports.location = false;
    this.$dayspan.supports.busy = false;
    this.$dayspan.supports.icon = false;
    this.$dayspan.supports.calendar = false;
    this.$dayspan.supports.description = false;

    this.$dayspan.features.drag = false;
    this.$dayspan.features.forecast = false;
    this.$dayspan.features.move = false;
    this.$dayspan.isValidEvent = (details, schedule, calendarEvent) => { return true; };
    this.$dayspan.getDefaultEventColor = () => this.getColorByType(this.defaultType);
    this.$dayspan.defaults.dsCalendarEventCreatePopover.labels.save = this.labels.enableHour;
    this.loadState();
    this.dConnect.loadPatients();
  },

  methods:
  {
    adding(day)
    {
      if (this.dConnect.adminOrHaveRole('counselor')) {
        this.resetCreatePopover();
      } else {
        this.$refs.app.$refs.calendar.clearPlaceholder();
      }
    },

    adminOrHaveRole(role)
    {
      return (this.currentLogin.current_user.roles.indexOf(role) > -1);
    },

    patientSelected(slotData)
    {
      slotData.details.color = this.getColorByType(
        (this.selectedPatient == 0)? 'hours' : 'sessions'
      );
      this.$dayspan.defaults.dsCalendarEventCreatePopover.labels.save =
          (this.selectedPatient == 0)?
          this.labels.enableHour :
          this.labels.createSession;
    },

    getCalendarTime(calendarEvent)
    {
      let sa = calendarEvent.start.format('a');
      let ea = calendarEvent.end.format('a');
      let sh = calendarEvent.start.format('h');
      let eh = calendarEvent.end.format('h');
      if (calendarEvent.start.minute !== 0)
      {
        sh += calendarEvent.start.format(':mm');
      }
      if (calendarEvent.end.minute !== 0)
      {
        eh += calendarEvent.end.format(':mm');
      }
      return (sa === ea) ? (sh + ' - ' + eh + ea) : (sh + sa + ' - ' + eh + ea);
    },

    setLocale(code)
    {
      moment.lang(code);
      this.$dayspan.setLocale(code);
      this.$dayspan.refreshTimes();
      this.$refs.app.$forceUpdate();
    },

    resetCreatePopover()
    {
      this.selectedPatient = 0;
      this.defaultDuration = 1;
      this.defaultDescription = '';
      this.$dayspan.defaults.dsCalendarEventCreatePopover.labels.save =
          this.labels.enableHour;
    },

    clearCreatePopover()
    {
      this.resetCreatePopover();
      this.$refs.app.$refs.calendar.clearPlaceholder();
    },

    getSelectedPatient()
    {
      let name = this.patients.find((item) => (item.value == this.selectedPatient)).text;
      return {
        pid: this.selectedPatient,
        name: name,
      };
    },

    async creating(ev)
    {
      let event = ev.calendarEvent.event;
      let day = ev.calendarEvent.day;
      event.schedule.times[0].dayOfMonth = day.dayOfMonth;
      event.schedule.times[0].year = day.year;
      event.schedule.times[0].month = day.month;
      let datetime = event.data.datetime = this.buildDateString(event);
      if (this.selectedPatient == 0) {
        event.data.type = "hours";
        ev.details.title = 'Available';
      } else {
        event.data.type = "sessions";
        let patient = this.getSelectedPatient().name;
        ev.details.title = patient;
      }
      let usr = await this.dConnect.currentUser();
      event.data.counselor = usr.uid;
      ev.calendarEvent.event.schedule.duration = this.defaultDuration;
      this.dConnect.createEventOnBackend(event);
    },

    buildDateString(item)
    {
      // 2017-07-21T18:11:11
      //return '2019-02-06T08:00:00';
      let time = item.schedule.times[0];
      let date = time.year + '-'
             + this.prependZero(time.month+1) + '-'
             + this.prependZero(time.dayOfMonth) + 'T'
             + this.prependZero(time.hour) + ':'
             + this.prependZero((typeof time.minute  === 'undefined')? 0 : time.minute)
             + ':00';
      return date;
    },
    prependZero(number)
    {
      return ("0" + number).slice(-2);
    },

    getColorByType(type)
    {
      let color = this.eventTypes[type].color;
      return this.$dayspan.colors.find((item) => (item.text == color)).value;
    },
    getColorByName(name) {
      return this.$dayspan.colors.find((item) => (item.text == name)).value;
    },

    saveState()
    {
      let state = this.calendar.toInput(true);
      let json = JSON.stringify(state);
      localStorage.setItem(this.storeKey, json);
    },

    cancelEvent(eventData) {
      let ev = eventData.calendarEvent.event;
      this.dConnect.removeEventFromBackend(ev);
      eventData.calendar.removeEvent( ev );
      eventData.close();
    },

    async loadState()
    {
      let state = {};

      try
      {
        let savedState = JSON.parse(localStorage.getItem(this.storeKey));

        if (savedState)
        {
          state = savedState;
          state.preferToday = false;
        }
      }
      catch (e)
      {
        // eslint-disable-next-line
        console.log( e );
      }
      let usr = await this.dConnect.currentUser();
      usr = usr.current_user;
      let auth = {
        username: this.dConnect.credentials.user,
        password: this.dConnect.credentials.pass
      };
      let headers = {
        'Content-Type': 'application/hal+json',
        //'X-CSRF-Token': csrf_token,
      };

      //this.paths.domain + this.paths.viewList + '/' + usr.uid


      await axios({
            url: this.dConnect.paths.domain + this.dConnect.paths.viewResults
                + '/session_calendar/page_1',
            method: 'get',
            headers: headers,
            auth: auth,
        })
        .then((response) => {
          let info = response.data;
          this.info = info;
          //state.events = this.defaultEvents.concat(response.data);
          state.events = response.data;
          state.events.forEach(ev =>
          {
            let defaults = this.$dayspan.getDefaultEventDetails();
            let type = (ev.data.type!='')? ev.data.type : this.defaultType;
            ev.data.color = this.getColorByType(type);



            switch (ev.data.type) {
              case 'hours':
                ev.data.title = 'Available';
              break;
              case 'sessions':
                ev.data.title = ev.data.title.split(' at ')[0].split(' & ')[0];
              break;
            }
            ev.data = Vue.util.extend( defaults, ev.data );
          });

          this.$refs.app.setState( state );
          return response.data;
        })
        .catch(err => console.log(err));

    },

    async __loadState()
    {
      let state = {};
      try
      {
        let savedState = JSON.parse(localStorage.getItem(this.storeKey));

        if (savedState)
        {
          state = savedState;
          state.preferToday = false;
        }
      }
      catch (e)
      {
        // eslint-disable-next-line
        console.log( e );
      }

      await this.dConnect.getEvents()
        .then((events) => {

          // eslint-disable-next-line
          console.log( events );

          let defaults = this.$dayspan.getDefaultEventDetails();
          state.events = events;
          state.events.forEach( ev => {

            switch (ev.data.type) {
              case 'hours':
                ev.data.title = 'Available';
              break;
              case 'sessions':
                ev.data.title = ev.data.title.split(' at ')[0].split(' & ')[0];
              break;
            }

            ev.data = Vue.util.extend( defaults, ev.data );

          });
          this.$refs.app.setState( state );
        });
    }
  },

}
</script>

<style>

body, html, #app {
  width: 100%;
  height: 100%;
}

.application--wrap {
  overflow: hidden;
  position: relative;
}

.v-navigation-drawer.v-navigation-drawer--fixed {
  position: absolute;
}

.ds-app-calendar-toolbar.v-toolbar--fixed {
  position: absolute;
}

.v-menu__content.menuable__content__active {
  right: 0;
  left: auto !important;
  top: 52px !important;
}

.v-dialog__content {
  display: block;
  position: absolute !important;
  top: 0 !important;
  z-index: 999 !important;
}

</style>
